<?php

namespace WPFlashNotes\Repos;

defined( 'ABSPATH' ) || exit;

use Exception;
use wpdb;

/**
 * CardSetRelationsRepository
 *
 * Repository for {prefix}wpfn_card_set_relations (composite PK: card_id,set_id).
 * Provides attach/detach/existence helpers plus list & sync operations.
 *
 * Notes:
 * - We DON'T extend BaseRepository because that class assumes a single integer PK "id".
 * - Uses INSERT IGNORE for idempotent attach (no error on duplicates).
 * - Respects FK constraints defined in schema tasks.
 */
class CardSetRelationsRepository {

	/**
	 * @var wpdb
	 */
	protected wpdb $wpdb;

	/**
	 * Full table name with prefix.
	 */
	protected string $table;

	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'wpfn_card_set_relations';
	}

	/**
	 * Idempotent link: (card_id, set_id).
	 * Returns true if inserted or already existing.
	 */
	public function attach( int $card_id, int $set_id ): bool {
		$card_id = $this->validate_id( $card_id );
		$set_id  = $this->validate_id( $set_id );

		// INSERT IGNORE avoids duplicate key errors for existing links
		$sql = $this->wpdb->prepare(
			"INSERT IGNORE INTO {$this->table} (card_id, set_id) VALUES (%d, %d)",
			$card_id,
			$set_id
		);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$res = $this->wpdb->query( $sql );
		if ( $res === false ) {
			throw new Exception( 'Attach failed: ' . ( $this->wpdb->last_error ?: 'unknown DB error' ) );
		}
		// res = 1 (inserted) or 0 (ignored duplicate) -> both are fine
		return $res >= 0;
	}

	/**
	 * Remove link. Returns true if a row was deleted.
	 */
	public function detach( int $card_id, int $set_id ): bool {
		$card_id = $this->validate_id( $card_id );
		$set_id  = $this->validate_id( $set_id );

		$res = $this->wpdb->delete(
			$this->table,
			array(
				'card_id' => $card_id,
				'set_id'  => $set_id,
			),
			array( '%d', '%d' )
		);
		if ( $res === false ) {
			throw new Exception( 'Detach failed: ' . ( $this->wpdb->last_error ?: 'unknown DB error' ) );
		}
		return $res > 0;
	}

	/**
	 * Check if link exists.
	 */
	public function exists( int $card_id, int $set_id ): bool {
		$card_id = $this->validate_id( $card_id );
		$set_id  = $this->validate_id( $set_id );

		$sql = $this->wpdb->prepare(
			"SELECT 1 FROM {$this->table} WHERE card_id = %d AND set_id = %d LIMIT 1",
			$card_id,
			$set_id
		);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$val = $this->wpdb->get_var( $sql );
		return (bool) $val;
	}

	/**
	 * Get all card IDs belonging to a set.
	 *
	 * @return int[]
	 */
	public function get_card_ids_for_set( int $set_id ): array {
		$set_id = $this->validate_id( $set_id );

		$sql = $this->wpdb->prepare(
			"SELECT card_id FROM {$this->table} WHERE set_id = %d ORDER BY card_id ASC",
			$set_id
		);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_col( $sql );
		return array_map( 'intval', $rows ?: array() );
	}

	/**
	 * Get all set IDs containing a card.
	 *
	 * @return int[]
	 */
	public function get_set_ids_for_card( int $card_id ): array {
		$card_id = $this->validate_id( $card_id );

		$sql = $this->wpdb->prepare(
			"SELECT set_id FROM {$this->table} WHERE card_id = %d ORDER BY set_id ASC",
			$card_id
		);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_col( $sql );
		return array_map( 'intval', $rows ?: array() );
	}

	/**
	 * Count cards in a set.
	 */
	public function count_for_set( int $set_id ): int {
		$set_id = $this->validate_id( $set_id );
		$sql    = $this->wpdb->prepare( "SELECT COUNT(*) FROM {$this->table} WHERE set_id = %d", $set_id );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $this->wpdb->get_var( $sql );
	}

	/**
	 * Count sets that include a card.
	 */
	public function count_for_card( int $card_id ): int {
		$card_id = $this->validate_id( $card_id );
		$sql     = $this->wpdb->prepare( "SELECT COUNT(*) FROM {$this->table} WHERE card_id = %d", $card_id );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $this->wpdb->get_var( $sql );
	}

	/**
	 * Bulk attach a list of cards to a set (idempotent).
	 * Returns number of successful inserts (duplicates ignored by MySQL).
	 */
	public function bulk_attach( int $set_id, array $card_ids ): int {
		$set_id   = $this->validate_id( $set_id );
		$card_ids = $this->normalize_ids( $card_ids );
		if ( ! $card_ids ) {
			return 0;
		}

		$inserted = 0;
		foreach ( array_chunk( $card_ids, 200 ) as $chunk ) {
			$values       = array();
			$placeholders = array();

			foreach ( $chunk as $cid ) {
				$values[]       = $cid;
				$values[]       = $set_id;
				$placeholders[] = '(%d,%d)';
			}

			$sql = "INSERT IGNORE INTO {$this->table} (card_id,set_id) VALUES " . implode( ',', $placeholders );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$res = $this->wpdb->query( $this->wpdb->prepare( $sql, ...$values ) );
			if ( $res === false ) {
				throw new Exception( 'Bulk attach failed: ' . ( $this->wpdb->last_error ?: 'unknown DB error' ) );
			}
			// INSERT IGNORE returns # actually inserted (0..N)
			$inserted += (int) $res;
		}

		return $inserted;
	}

	/**
	 * Remove all relations for set_id that are NOT in $desired_card_ids,
	 * and attach any missing ones. Returns [added, removed, kept].
	 */
	public function sync_set_cards( int $set_id, array $desired_card_ids ): array {
		$set_id  = $this->validate_id( $set_id );
		$desired = $this->normalize_ids( $desired_card_ids );

		$current     = $this->get_card_ids_for_set( $set_id );
		$current_map = array_fill_keys( $current, true );
		$desired_map = array_fill_keys( $desired, true );

		$to_add    = array_values( array_diff( $desired, $current ) );
		$to_remove = array_values( array_diff( $current, $desired ) );
		$kept      = count( $current ) - count( $to_remove );

		// Remove
		if ( $to_remove ) {
			foreach ( array_chunk( $to_remove, 500 ) as $chunk ) {
				$in  = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
				$sql = $this->wpdb->prepare(
					"DELETE FROM {$this->table} WHERE set_id = %d AND card_id IN ($in)",
					$set_id,
					...$chunk
				);
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$res = $this->wpdb->query( $sql );
				if ( $res === false ) {
					throw new Exception( 'Sync remove failed: ' . ( $this->wpdb->last_error ?: 'unknown DB error' ) );
				}
			}
		}

		// Add
		$added = $this->bulk_attach( $set_id, $to_add );

		return array(
			'added'   => (int) $added,
			'removed' => (int) count( $to_remove ),
			'kept'    => (int) $kept,
		);
	}

	/**
	 * Danger zone: remove ALL card links for a set.
	 */
	public function clear_set( int $set_id ): int {
		$set_id = $this->validate_id( $set_id );
		$res    = $this->wpdb->delete( $this->table, array( 'set_id' => $set_id ), array( '%d' ) );
		if ( $res === false ) {
			throw new Exception( 'Clear set failed: ' . ( $this->wpdb->last_error ?: 'unknown DB error' ) );
		}
		return (int) $res;
	}

	// --- Helpers ------------------------------------------------------------

	protected function validate_id( int $id ): int {
		$id = absint( $id );
		if ( $id <= 0 ) {
			throw new Exception( 'ID must be a positive integer.' );
		}
		return $id;
	}

	/**
	 * Normalize an array of ints: positive, unique, sorted ASC.
	 *
	 * @param array<int|numeric-string> $ids
	 * @return int[]
	 */
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
