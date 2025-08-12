<?php

namespace WPFlashNotes\Repos;

defined('ABSPATH') || exit;

use Exception;
use wpdb;

/**
 * NoteSetRelationsRepository
 *
 * Table: {prefix}wpfn_note_set_relations
 * Columns / PK: (note_id BIGINT UNSIGNED, set_id BIGINT UNSIGNED) PRIMARY KEY(note_id,set_id)
 * FKs: note_id -> {prefix}wpfn_notes.id, set_id -> {prefix}wpfn_sets.id
 *
 * WHY NOT BaseRepository?
 * - BaseRepository is built around a single 'id' PK, optional soft deletes, and timestamp fields.
 * - This pivot uses a composite PK, has no timestamps/soft-delete, and needs idempotent attach/detach
 *   plus bulk/sync operations—cleaner with a dedicated repo.
 */
class NoteSetRelationsRepository
{
    protected wpdb $wpdb;
    protected string $table;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb  = $wpdb;
        $this->table = $wpdb->prefix . 'wpfn_note_set_relations';
    }

    /** Idempotent link: (note_id, set_id). */
    public function attach(int $note_id, int $set_id): bool
    {
        $note_id = $this->validate_id($note_id);
        $set_id  = $this->validate_id($set_id);

        $sql = $this->wpdb->prepare(
            "INSERT IGNORE INTO {$this->table} (note_id, set_id) VALUES (%d, %d)",
            $note_id,
            $set_id
        );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $res = $this->wpdb->query($sql);
        if ($res === false) {
            throw new Exception('Attach failed: ' . ($this->wpdb->last_error ?: 'unknown DB error'));
        }
        return $res >= 0;
    }

    /** Remove link. */
    public function detach(int $note_id, int $set_id): bool
    {
        $note_id = $this->validate_id($note_id);
        $set_id  = $this->validate_id($set_id);

        $res = $this->wpdb->delete($this->table, ['note_id' => $note_id, 'set_id' => $set_id], ['%d','%d']);
        if ($res === false) {
            throw new Exception('Detach failed: ' . ($this->wpdb->last_error ?: 'unknown DB error'));
        }
        return $res > 0;
    }

    /** Check existence. */
    public function exists(int $note_id, int $set_id): bool
    {
        $note_id = $this->validate_id($note_id);
        $set_id  = $this->validate_id($set_id);

        $sql = $this->wpdb->prepare(
            "SELECT 1 FROM {$this->table} WHERE note_id = %d AND set_id = %d LIMIT 1",
            $note_id,
            $set_id
        );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return (bool) $this->wpdb->get_var($sql);
    }

    /** @return int[] Notes in set. */
    public function get_note_ids_for_set(int $set_id): array
    {
        $set_id = $this->validate_id($set_id);
        $sql = $this->wpdb->prepare(
            "SELECT note_id FROM {$this->table} WHERE set_id = %d ORDER BY note_id ASC",
            $set_id
        );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $this->wpdb->get_col($sql);
        return array_map('intval', $rows ?: []);
    }

    /** @return int[] Sets containing note. */
    public function get_set_ids_for_note(int $note_id): array
    {
        $note_id = $this->validate_id($note_id);
        $sql = $this->wpdb->prepare(
            "SELECT set_id FROM {$this->table} WHERE note_id = %d ORDER BY set_id ASC",
            $note_id
        );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $this->wpdb->get_col($sql);
        return array_map('intval', $rows ?: []);
    }

    public function count_for_set(int $set_id): int
    {
        $set_id = $this->validate_id($set_id);
        $sql = $this->wpdb->prepare("SELECT COUNT(*) FROM {$this->table} WHERE set_id = %d", $set_id);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return (int) $this->wpdb->get_var($sql);
    }

    public function count_for_note(int $note_id): int
    {
        $note_id = $this->validate_id($note_id);
        $sql = $this->wpdb->prepare("SELECT COUNT(*) FROM {$this->table} WHERE note_id = %d", $note_id);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return (int) $this->wpdb->get_var($sql);
    }

    /** Bulk attach (idempotent). */
    public function bulk_attach(int $set_id, array $note_ids): int
    {
        $set_id = $this->validate_id($set_id);
        $note_ids = $this->normalize_ids($note_ids);
        if (!$note_ids) return 0;

        $inserted = 0;
        foreach (array_chunk($note_ids, 200) as $chunk) {
            $values = [];
            $ph = [];
            foreach ($chunk as $nid) {
                $values[] = $nid;
                $values[] = $set_id;
                $ph[] = '(%d,%d)';
            }
            $sql = "INSERT IGNORE INTO {$this->table} (note_id,set_id) VALUES " . implode(',', $ph);
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $res = $this->wpdb->query($this->wpdb->prepare($sql, ...$values));
            if ($res === false) {
                throw new Exception('Bulk attach failed: ' . ($this->wpdb->last_error ?: 'unknown DB error'));
            }
            $inserted += (int) $res;
        }
        return $inserted;
    }

    /** Sync to exactly $desired_note_ids for a set. */
    public function sync_set_notes(int $set_id, array $desired_note_ids): array
    {
        $set_id  = $this->validate_id($set_id);
        $desired = $this->normalize_ids($desired_note_ids);

        $current   = $this->get_note_ids_for_set($set_id);
        $to_add    = array_values(array_diff($desired, $current));
        $to_remove = array_values(array_diff($current, $desired));
        $kept      = count($current) - count($to_remove);

        if ($to_remove) {
            foreach (array_chunk($to_remove, 500) as $chunk) {
                $in  = implode(',', array_fill(0, count($chunk), '%d'));
                $sql = $this->wpdb->prepare(
                    "DELETE FROM {$this->table} WHERE set_id = %d AND note_id IN ($in)",
                    $set_id,
                    ...$chunk
                );
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $res = $this->wpdb->query($sql);
                if ($res === false) {
                    throw new Exception('Sync remove failed: ' . ($this->wpdb->last_error ?: 'unknown DB error'));
                }
            }
        }

        $added = $this->bulk_attach($set_id, $to_add);

        return ['added' => (int) $added, 'removed' => (int) count($to_remove), 'kept' => (int) $kept];
    }

    /** Remove ALL note links for a set. */
    public function clear_set(int $set_id): int
    {
        $set_id = $this->validate_id($set_id);
        $res = $this->wpdb->delete($this->table, ['set_id' => $set_id], ['%d']);
        if ($res === false) {
            throw new Exception('Clear set failed: ' . ($this->wpdb->last_error ?: 'unknown DB error'));
        }
        return (int) $res;
    }

    // Helpers

    protected function validate_id(int $id): int
    {
        $id = absint($id);
        if ($id <= 0) throw new Exception('ID must be a positive integer.');
        return $id;
    }

    protected function normalize_ids(array $ids): array
    {
        $out = [];
        foreach ($ids as $v) {
            $i = absint($v);
            if ($i > 0) $out[$i] = true;
        }
        $list = array_keys($out);
        sort($list, SORT_NUMERIC);
        return $list;
    }
}
