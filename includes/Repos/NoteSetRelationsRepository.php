<?php

namespace WPFlashNotes\Repos;

defined( 'ABSPATH' ) || exit;

use wpdb;
use WPFlashNotes\Errors\WPFlashNotesError;

/**
 * NoteSetRelationsRepository
 *
 * Table: {prefix}wpfn_note_set_relations
 * Composite PK: (note_id, set_id)
 * - note_id → wpfn_notes.id
 * - set_id  → wpfn_sets.id
 *
 * Not using BaseRepository because this pivot:
 *  - has a composite PK
 *  - has no timestamps or soft deletes
 *  - requires idempotent attach/detach and sync operations
 */
class NoteSetRelationsRepository {

	protected wpdb $wpdb;
	protected string $table;

	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'wpfn_note_set_relations';
	}

	/** Idempotent link (note_id, set_id). */
	public function attach( int $note_id, int $set_id ): bool {
		$note_id = $this->validate_id( $note_id );
		$set_id  = $this->validate_id( $set_id );

		$sql = $this->wpdb->prepare(
			"INSERT IGNORE INTO {$this->table} (note_id, set_id) VALUES (%d, %d)",
			$note_id,
			$set_id
		);

		$res = $this->wpdb->query( $sql );
		if ( $res === false ) {
			throw new WPFlashNotesError(
				'db',
				'Attach failed: ' . ( $this->wpdb->last_error ?: 'unknown DB error' ),
				500
			);
		}
		return $res >= 0;
	}

	/** Remove link. */
	public function detach( int $note_id, int $set_id ): bool {
		$note_id = $this->validate_id( $note_id );
		$set_id  = $this->validate_id( $set_id );

		$res = $this->wpdb->delete(
			$this->table,
			array( 'note_id' => $note_id, 'set_id' => $set_id ),
			array( '%d', '%d' )
		);

		if ( $res === false ) {
			throw new WPFlashNotesError(
				'db',
				'Detach failed: ' . ( $this->wpdb->last_error ?: 'unknown DB error' ),
				500
			);
		}
		return $res > 0;
	}

	/** Check existence. */
	public function exists( int $note_id, int $set_id ): bool {
		$note_id = $this->validate_id( $note_id );
		$set_id  = $this->validate_id( $set_id );

		$sql = $this->wpdb->prepare(
			"SELECT 1 FROM {$this->table} WHERE note_id = %d AND set_id = %d LIMIT 1",
			$note_id,
			$set_id
		);

		return (bool) $this->wpdb->get_var( $sql );
	}

	/** @return int[] Notes in set. */
	public function get_note_ids_for_set( int $set_id ): array {
		$set_id = $this->validate_id( $set_id );

		$sql  = $this->wpdb->prepare(
			"SELECT note_id FROM {$this->table} WHERE set_id = %d ORDER BY note_id ASC",
			$set_id
		);
		$rows = $this->wpdb->get_col( $sql );

		if ( $rows === null && $this->wpdb->last_error ) {
			throw new WPFlashNotesError(
				'db',
				'Failed to fetch note IDs: ' . $this->wpdb->last_error,
				500
			);
		}

		return array_map( 'intval', $rows ?: array() );
	}

	/** @return int[] Sets containing note. */
	public function get_set_ids_for_note( int $note_id ): array {
		$note_id = $this->validate_id( $note_id );

		$sql  = $this->wpdb->prepare(
			"SELECT set_id FROM {$this->table} WHERE note_id = %d ORDER BY set_id ASC",
			$note_id
		);
		$rows = $this->wpdb->get_col( $sql );

		if ( $rows === null && $this->wpdb->last_error ) {
			throw new WPFlashNotesError(
				'db',
				'Failed to fetch set IDs: ' . $this->wpdb->last_error,
				500
			);
		}

		return array_map( 'intval', $rows ?: array() );
	}

	public function count_for_set( int $set_id ): int {
		$set_id = $this->validate_id( $set_id );
		$sql    = $this->wpdb->prepare( "SELECT COUNT(*) FROM {$this->table} WHERE set_id = %d", $set_id );

		$count = $this->wpdb->get_var( $sql );
		if ( $count === null && $this->wpdb->last_error ) {
			throw new WPFlashNotesError(
				'db',
				'Count failed: ' . $this->wpdb->last_error,
				500
			);
		}
		return (int) $count;
	}

	public function count_for_note( int $note_id ): int {
		$note_id = $this->validate_id( $note_id );
		$sql     = $this->wpdb->prepare( "SELECT COUNT(*) FROM {$this->table} WHERE note_id = %d", $note_id );

		$count = $this->wpdb->get_var( $sql );
		if ( $count === null && $this->wpdb->last_error ) {
			throw new WPFlashNotesError(
				'db',
				'Count failed: ' . $this->wpdb->last_error,
				500
			);
		}
		return (int) $count;
	}

	/** Bulk attach (idempotent). */
	public function bulk_attach( int $set_id, array $note_ids ): int {
		$set_id   = $this->validate_id( $set_id );
		$note_ids = $this->normalize_ids( $note_ids );

		if ( ! $note_ids ) {
			return 0;
		}

		$total = 0;
		foreach ( array_chunk( $note_ids, 200 ) as $chunk ) {
			$values = array();
			$ph     = array();

			foreach ( $chunk as $nid ) {
				$values[] = $nid;
				$values[] = $set_id;
				$ph[]     = '(%d,%d)';
			}

			$sql = "INSERT IGNORE INTO {$this->table} (note_id,set_id) VALUES " . implode( ',', $ph );
			$res = $this->wpdb->query( $this->wpdb->prepare( $sql, ...$values ) );

			if ( $res === false ) {
				throw new WPFlashNotesError(
					'db',
					'Bulk attach failed: ' . ( $this->wpdb->last_error ?: 'unknown DB error' ),
					500
				);
			}

			$total += (int) $res;
		}
		return $total;
	}

	/** Sync to exactly $desired_note_ids for a set. */
	public function sync_set_notes( int $set_id, array $desired_note_ids ): array {
		$set_id  = $this->validate_id( $set_id );
		$desired = $this->normalize_ids( $desired_note_ids );

		$current   = $this->get_note_ids_for_set( $set_id );
		$to_add    = array_values( array_diff( $desired, $current ) );
		$to_remove = array_values( array_diff( $current, $desired ) );
		$kept      = count( $current ) - count( $to_remove );

		if ( $to_remove ) {
			foreach ( array_chunk( $to_remove, 500 ) as $chunk ) {
				$in  = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
				$sql = $this->wpdb->prepare(
					"DELETE FROM {$this->table} WHERE set_id = %d AND note_id IN ($in)",
					$set_id,
					...$chunk
				);

				$res = $this->wpdb->query( $sql );
				if ( $res === false ) {
					throw new WPFlashNotesError(
						'db',
						'Sync remove failed: ' . ( $this->wpdb->last_error ?: 'unknown DB error' ),
						500
					);
				}
			}
		}

		$added = $this->bulk_attach( $set_id, $to_add );

		return array(
			'added'   => (int) $added,
			'removed' => (int) count( $to_remove ),
			'kept'    => (int) $kept,
		);
	}

	/** Remove ALL note links for a set. */
	public function clear_set( int $set_id ): int {
		$set_id = $this->validate_id( $set_id );
		$res    = $this->wpdb->delete( $this->table, array( 'set_id' => $set_id ), array( '%d' ) );

		if ( $res === false ) {
			throw new WPFlashNotesError(
				'db',
				'Clear set failed: ' . ( $this->wpdb->last_error ?: 'unknown DB error' ),
				500
			);
		}
		return (int) $res;
	}

	// Helpers
	protected function validate_id( int $id ): int {
		$id = absint( $id );
		if ( $id <= 0 ) {
			throw new WPFlashNotesError(
				'validation',
				'ID must be a positive integer.',
				400
			);
		}
		return $id;
	}

	protected function normalize_ids( array $ids ): array {
		$out = array();
		foreach ( $ids as $v ) {
			$i = absint( $v );
			if ( $i > 0 ) {
				$out[ $i ] = true;
			}
		}
		$list = array_keys( $out );
		sort( $list, SORT_NUMERIC );
		return $list;
	}
}
