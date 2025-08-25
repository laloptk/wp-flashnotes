<?php

namespace WPFlashNotes\Repos;

defined( 'ABSPATH' ) || exit;

use Exception;
use wpdb;

/**
 * TaxonomyRelationsRepository
 *
 * Table: {prefix}wpfn_taxonomy_relations
 * Columns / PK: (object_type ENUM('set','card','note'), object_id BIGINT, term_taxonomy_id BIGINT)
 *              PRIMARY KEY(object_type, object_id, term_taxonomy_id)
 * FK: term_taxonomy_id -> {prefix}term_taxonomy.term_taxonomy_id
 *
 * WHY NOT BaseRepository?
 * - BaseRepository targets single 'id' PK tables with timestamp/soft-delete ergonomics.
 * - This pivot has a composite PK and focuses on idempotent attach/detach, bulk insert-ignore,
 *   and sync operations. A dedicated repo is simpler and avoids leaky abstractions.
 */
class TaxonomyRelationsRepository {

	protected wpdb $wpdb;
	protected string $table;
	/** @var array<string,bool> */
	protected array $allowedTypes = array(
		'set'  => true,
		'card' => true,
		'note' => true,
	);

	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'wpfn_taxonomy_relations';
	}

	// Core

	public function attach( string $object_type, int $object_id, int $tt_id ): bool {
		$object_type = $this->validate_type( $object_type );
		$object_id   = $this->validate_id( $object_id );
		$tt_id       = $this->validate_id( $tt_id );

		$sql = $this->wpdb->prepare(
			"INSERT IGNORE INTO {$this->table} (object_type, object_id, term_taxonomy_id) VALUES (%s, %d, %d)",
			$object_type,
			$object_id,
			$tt_id
		);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$res = $this->wpdb->query( $sql );
		if ( $res === false ) {
			throw new Exception( 'Attach failed: ' . ( $this->wpdb->last_error ?: 'unknown DB error' ) );
		}
		return $res >= 0;
	}

	public function detach( string $object_type, int $object_id, int $tt_id ): bool {
		$object_type = $this->validate_type( $object_type );
		$object_id   = $this->validate_id( $object_id );
		$tt_id       = $this->validate_id( $tt_id );

		$res = $this->wpdb->delete(
			$this->table,
			array(
				'object_type'      => $object_type,
				'object_id'        => $object_id,
				'term_taxonomy_id' => $tt_id,
			),
			array( '%s', '%d', '%d' )
		);
		if ( $res === false ) {
			throw new Exception( 'Detach failed: ' . ( $this->wpdb->last_error ?: 'unknown DB error' ) );
		}
		return $res > 0;
	}

	public function exists( string $object_type, int $object_id, int $tt_id ): bool {
		$object_type = $this->validate_type( $object_type );
		$object_id   = $this->validate_id( $object_id );
		$tt_id       = $this->validate_id( $tt_id );

		$sql = $this->wpdb->prepare(
			"SELECT 1 FROM {$this->table} WHERE object_type = %s AND object_id = %d AND term_taxonomy_id = %d LIMIT 1",
			$object_type,
			$object_id,
			$tt_id
		);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (bool) $this->wpdb->get_var( $sql );
	}

	// Lists & counts

	/** @return int[] term_taxonomy_ids for an object */
	public function get_tt_ids_for_object( string $object_type, int $object_id ): array {
		$object_type = $this->validate_type( $object_type );
		$object_id   = $this->validate_id( $object_id );

		$sql = $this->wpdb->prepare(
			"SELECT term_taxonomy_id FROM {$this->table} WHERE object_type = %s AND object_id = %d ORDER BY term_taxonomy_id ASC",
			$object_type,
			$object_id
		);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_col( $sql );
		return array_map( 'intval', $rows ?: array() );
	}

	/** @return int[] object_ids that have a given term */
	public function get_object_ids_for_tt( string $object_type, int $tt_id ): array {
		$object_type = $this->validate_type( $object_type );
		$tt_id       = $this->validate_id( $tt_id );

		$sql = $this->wpdb->prepare(
			"SELECT object_id FROM {$this->table} WHERE object_type = %s AND term_taxonomy_id = %d ORDER BY object_id ASC",
			$object_type,
			$tt_id
		);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_col( $sql );
		return array_map( 'intval', $rows ?: array() );
	}

	public function count_for_object( string $object_type, int $object_id ): int {
		$object_type = $this->validate_type( $object_type );
		$object_id   = $this->validate_id( $object_id );

		$sql = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table} WHERE object_type = %s AND object_id = %d",
			$object_type,
			$object_id
		);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $this->wpdb->get_var( $sql );
	}

	public function count_for_tt( string $object_type, int $tt_id ): int {
		$object_type = $this->validate_type( $object_type );
		$tt_id       = $this->validate_id( $tt_id );

		$sql = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table} WHERE object_type = %s AND term_taxonomy_id = %d",
			$object_type,
			$tt_id
		);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $this->wpdb->get_var( $sql );
	}

	// Bulk & sync

	/** Bulk attach terms to an object (idempotent). Returns number of new rows. */
	public function bulk_attach( string $object_type, int $object_id, array $tt_ids ): int {
		$object_type = $this->validate_type( $object_type );
		$object_id   = $this->validate_id( $object_id );
		$tt_ids      = $this->normalize_ids( $tt_ids );
		if ( ! $tt_ids ) {
			return 0;
		}

		$inserted = 0;
		foreach ( array_chunk( $tt_ids, 200 ) as $chunk ) {
			$vals = array();
			$ph   = array();
			foreach ( $chunk as $tt ) {
				$vals[] = $object_type;
				$vals[] = $object_id;
				$vals[] = $tt;
				$ph[]   = '(%s,%d,%d)';
			}
			$sql = "INSERT IGNORE INTO {$this->table} (object_type, object_id, term_taxonomy_id) VALUES " . implode( ',', $ph );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$res = $this->wpdb->query( $this->wpdb->prepare( $sql, ...$vals ) );
			if ( $res === false ) {
				throw new Exception( 'Bulk attach failed: ' . ( $this->wpdb->last_error ?: 'unknown DB error' ) );
			}
			$inserted += (int) $res;
		}
		return $inserted;
	}

	/**
	 * Sync an object's terms to exactly $desired_tt_ids.
	 * Returns ['added'=>int,'removed'=>int,'kept'=>int].
	 */
	public function sync_object_terms( string $object_type, int $object_id, array $desired_tt_ids ): array {
		$object_type = $this->validate_type( $object_type );
		$object_id   = $this->validate_id( $object_id );
		$desired     = $this->normalize_ids( $desired_tt_ids );

		$current   = $this->get_tt_ids_for_object( $object_type, $object_id );
		$to_add    = array_values( array_diff( $desired, $current ) );
		$to_remove = array_values( array_diff( $current, $desired ) );
		$kept      = count( $current ) - count( $to_remove );

		if ( $to_remove ) {
			foreach ( array_chunk( $to_remove, 500 ) as $chunk ) {
				$in  = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
				$sql = $this->wpdb->prepare(
					"DELETE FROM {$this->table}
                     WHERE object_type = %s AND object_id = %d AND term_taxonomy_id IN ($in)",
					$object_type,
					$object_id,
					...$chunk
				);
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$res = $this->wpdb->query( $sql );
				if ( $res === false ) {
					throw new Exception( 'Sync remove failed: ' . ( $this->wpdb->last_error ?: 'unknown DB error' ) );
				}
			}
		}

		$added = $this->bulk_attach( $object_type, $object_id, $to_add );

		return array(
			'added'   => (int) $added,
			'removed' => (int) count( $to_remove ),
			'kept'    => (int) $kept,
		);
	}

	/** Remove ALL term links for an object. */
	public function clear_object( string $object_type, int $object_id ): int {
		$object_type = $this->validate_type( $object_type );
		$object_id   = $this->validate_id( $object_id );

		$res = $this->wpdb->delete(
			$this->table,
			array(
				'object_type' => $object_type,
				'object_id'   => $object_id,
			),
			array( '%s', '%d' )
		);
		if ( $res === false ) {
			throw new Exception( 'Clear object failed: ' . ( $this->wpdb->last_error ?: 'unknown DB error' ) );
		}
		return (int) $res;
	}

	/** Remove ALL object links for a term. */
	public function clear_term( string $object_type, int $tt_id ): int {
		$object_type = $this->validate_type( $object_type );
		$tt_id       = $this->validate_id( $tt_id );

		$res = $this->wpdb->delete(
			$this->table,
			array(
				'object_type'      => $object_type,
				'term_taxonomy_id' => $tt_id,
			),
			array( '%s', '%d' )
		);
		if ( $res === false ) {
			throw new Exception( 'Clear term failed: ' . ( $this->wpdb->last_error ?: 'unknown DB error' ) );
		}
		return (int) $res;
	}

	// Helpers

	protected function validate_type( string $type ): string {
		$type = strtolower( trim( $type ) );
		if ( ! isset( $this->allowedTypes[ $type ] ) ) {
			throw new Exception( 'Invalid object_type. Allowed: set, card, note.' );
		}
		return $type;
	}

	protected function validate_id( int $id ): int {
		$id = absint( $id );
		if ( $id <= 0 ) {
			throw new Exception( 'ID must be a positive integer.' );
		}
		return $id;
	}

	/** unique, positive, sorted */
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
