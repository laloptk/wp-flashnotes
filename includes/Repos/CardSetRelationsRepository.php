<?php

namespace WPFlashNotes\Repos;

defined( 'ABSPATH' ) || exit;

use wpdb;
use WPFlashNotes\Errors\WPFlashNotesError;

/**
 * CardSetRelationsRepository
 *
 * Repository for {prefix}wpfn_card_set_relations (composite PK: card_id,set_id).
 * Provides attach/detach/existence helpers plus list & sync operations.
 *
 * Notes:
 * - Not extending BaseRepository (composite PK, no timestamps/soft delete).
 * - INSERT IGNORE ensures idempotent attach (no duplicate key errors).
 * - Respects FK constraints defined in schema tasks.
 */
class CardSetRelationsRepository {

	protected wpdb $wpdb;
	protected string $table;

	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'wpfn_card_set_relations';
	}

	/** Idempotent link: (card_id, set_id). */
	public function attach( int $card_id, int $set_id ): bool {
		$card_id = $this->validate_id( $card_id );
		$set_id  = $this->validate_id( $set_id );

		$sql = $this->wpdb->prepare(
			"INSERT IGNORE INTO {$this->table} (card_id, set_id) VALUES (%d, %d)",
			$card_id,
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
	public function detach( int $card_id, int $set_id ): bool {
		$card_id = $this->validate_id( $card_id );
		$set_id  = $this->validate_id( $set_id );

		$res = $this->wpdb->delete(
			$this->table,
			array( 'card_id' => $card_id, 'set_id' => $set_id ),
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
	public function exists( int $card_id, int $set_id ): bool {
		$card_id = $this->validate_id( $card_id );
		$set_id  = $this->validate_id( $set_id );

		$sql = $this->wpdb->prepare(
			"SELECT 1 FROM {$this->table} WHERE card_id = %d AND set_id = %d LIMIT 1",
			$card_id,
			$set_id
		);
		return (bool) $this->wpdb->get_var( $sql );
	}

	/** @return int[] Card IDs in set. */
	public function get_card_ids_for_set( int $set_id ): array {
		$set_id = $this->validate_id( $set_id );

		$sql  = $this->wpdb->prepare(
			"SELECT card_id FROM {$this->table} WHERE set_id = %d ORDER BY card_id ASC",
			$set_id
		);
		$rows = $this->wpdb->get_col( $sql );

		if ( $rows === null && $this->wpdb->last_error ) {
			throw new WPFlashNotesError(
				'db',
				'Failed to fetch card IDs: ' . $this->wpdb->last_error,
				500
			);
		}

		return array_map( 'intval', $rows ?: array() );
	}

	/** @return int[] Set IDs containing a card. */
	public function get_set_ids_for_card( int $card_id ): array {
		$card_id = $this->validate_id( $card_id );

		$sql  = $this->wpdb->prepare(
			"SELECT set_id FROM {$this->table} WHERE card_id = %d ORDER BY set_id ASC",
			$card_id
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
		$count  = $this->wpdb->get_var( $sql );

		if ( $count === null && $this->wpdb->last_error ) {
			throw new WPFlashNotesError(
				'db',
				'Count failed: ' . $this->wpdb->last_error,
				500
			);
		}
		return (int) $count;
	}

	public function count_for_card( int $card_id ): int {
		$card_id = $this->validate_id( $card_id );
		$sql     = $this->wpdb->prepare( "SELECT COUNT(*) FROM {$this->table} WHERE card_id = %d", $card_id );
		$count   = $this->wpdb->get_var( $sql );

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
	public function bulk_attach( int $set_id, array $card_ids ): int {
		$set_id   = $this->validate_id( $set_id );
		$card_ids = $this->normalize_ids( $card_ids );

		if ( ! $card_ids ) {
			return 0;
		}

		$total = 0;
		foreach ( array_chunk( $card_ids, 200 ) as $chunk ) {
			$values = array();
			$ph     = array();

			foreach ( $chunk as $cid ) {
				$values[] = $cid;
				$values[] = $set_id;
				$ph[]     = '(%d,%d)';
			}

			$sql = "INSERT IGNORE INTO {$this->table} (card_id,set_id) VALUES " . implode( ',', $ph );
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

	/** Sync to exactly $desired_card_ids for a set. */
	public function sync_set_cards( int $set_id, array $desired_card_ids ): array {
		$set_id  = $this->validate_id( $set_id );
		$desired = $this->normalize_ids( $desired_card_ids );

		$current   = $this->get_card_ids_for_set( $set_id );
		$to_add    = array_values( array_diff( $desired, $current ) );
		$to_remove = array_values( array_diff( $current, $desired ) );
		$kept      = count( $current ) - count( $to_remove );

		if ( $to_remove ) {
			foreach ( array_chunk( $to_remove, 500 ) as $chunk ) {
				$in  = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
				$sql = $this->wpdb->prepare(
					"DELETE FROM {$this->table} WHERE set_id = %d AND card_id IN ($in)",
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

	/** Remove ALL card links for a set. */
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

	// --- Helpers ------------------------------------------------------------

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
