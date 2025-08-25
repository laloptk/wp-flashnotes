<?php

namespace WPFlashNotes\Repos;

defined( 'ABSPATH' ) || exit;

use Exception;
use wpdb;

/**
 * ObjectUsageRepository
 *
 * Table: {prefix}wpfn_object_usage
 * Columns / PK:
 *  - object_type ENUM('card','note') NOT NULL
 *  - object_id   BIGINT UNSIGNED NOT NULL
 *  - post_id     BIGINT UNSIGNED NOT NULL
 *  - block_id    VARCHAR(128)    NOT NULL
 *  PRIMARY KEY(object_type, object_id, post_id, block_id)
 * FK: post_id -> {prefix}posts.ID
 *
 * WHY NOT BaseRepository?
 * - BaseRepository assumes a single integer PK column named "id", optional soft-deletes,
 *   and timestamp handling with read/update/delete by that ID.
 * - This table has a COMPOSITE primary key (object_type, object_id, post_id, block_id),
 *   no timestamps/soft-deletes, and requires idempotent attach/detach, listings, and
 *   bulk/sync operations. A purpose-built repo is simpler and avoids forcing abstractions.
 */
class ObjectUsageRepository {

	/** @var wpdb */
	protected wpdb $wpdb;

	/** @var string */
	protected string $table;

	/** @var array<string,bool> */
	protected array $allowedTypes = array(
		'card' => true,
		'note' => true,
	);

	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'wpfn_object_usage';
	}

	// ---------------------------------------------------------------------
	// Core ops
	// ---------------------------------------------------------------------

	/** Idempotent link: (object_type, object_id) used in (post_id, block_id). */
	public function attach( string $object_type, int $object_id, int $post_id, string $block_id ): bool {
		$object_type = $this->validate_type( $object_type );
		$object_id   = $this->validate_id( $object_id );
		$post_id     = $this->validate_id( $post_id );
		$block_id    = $this->validate_block_id( $block_id );

		$sql = $this->wpdb->prepare(
			"INSERT IGNORE INTO {$this->table} (object_type, object_id, post_id, block_id) VALUES (%s, %d, %d, %s)",
			$object_type,
			$object_id,
			$post_id,
			$block_id
		);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$res = $this->wpdb->query( $sql );
		if ( $res === false ) {
			throw new Exception( 'Attach failed: ' . ( $this->wpdb->last_error ?: 'unknown DB error' ) );
		}
		return $res >= 0;
	}

	/** Remove one usage link. Returns true if a row was deleted. */
	public function detach( string $object_type, int $object_id, int $post_id, string $block_id ): bool {
		$object_type = $this->validate_type( $object_type );
		$object_id   = $this->validate_id( $object_id );
		$post_id     = $this->validate_id( $post_id );
		$block_id    = $this->validate_block_id( $block_id );

		$res = $this->wpdb->delete(
			$this->table,
			array(
				'object_type' => $object_type,
				'object_id'   => $object_id,
				'post_id'     => $post_id,
				'block_id'    => $block_id,
			),
			array( '%s', '%d', '%d', '%s' )
		);
		if ( $res === false ) {
			throw new Exception( 'Detach failed: ' . ( $this->wpdb->last_error ?: 'unknown DB error' ) );
		}
		return $res > 0;
	}

	/** Check if a specific usage exists. */
	public function exists( string $object_type, int $object_id, int $post_id, string $block_id ): bool {
		$object_type = $this->validate_type( $object_type );
		$object_id   = $this->validate_id( $object_id );
		$post_id     = $this->validate_id( $post_id );
		$block_id    = $this->validate_block_id( $block_id );

		$sql = $this->wpdb->prepare(
			"SELECT 1 FROM {$this->table}
             WHERE object_type = %s AND object_id = %d AND post_id = %d AND block_id = %s
             LIMIT 1",
			$object_type,
			$object_id,
			$post_id,
			$block_id
		);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (bool) $this->wpdb->get_var( $sql );
	}

	// ---------------------------------------------------------------------
	// Listings & counts
	// ---------------------------------------------------------------------

	/** @return string[] All block IDs where object appears in a given post. */
	public function get_block_ids_for_object_in_post( string $object_type, int $object_id, int $post_id ): array {
		$object_type = $this->validate_type( $object_type );
		$object_id   = $this->validate_id( $object_id );
		$post_id     = $this->validate_id( $post_id );

		$sql = $this->wpdb->prepare(
			"SELECT block_id FROM {$this->table}
             WHERE object_type = %s AND object_id = %d AND post_id = %d
             ORDER BY block_id ASC",
			$object_type,
			$object_id,
			$post_id
		);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_col( $sql ) ?: array();
		return array_values( array_map( 'strval', $rows ) );
	}

	/** @return int[] All post IDs where the object appears. */
	public function get_post_ids_for_object( string $object_type, int $object_id ): array {
		$object_type = $this->validate_type( $object_type );
		$object_id   = $this->validate_id( $object_id );

		$sql = $this->wpdb->prepare(
			"SELECT DISTINCT post_id FROM {$this->table}
             WHERE object_type = %s AND object_id = %d
             ORDER BY post_id ASC",
			$object_type,
			$object_id
		);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_col( $sql );
		return array_map( 'intval', $rows ?: array() );
	}

	/** @return int[] All object IDs of a type used in a post. */
	public function get_object_ids_for_post( string $object_type, int $post_id ): array {
		$object_type = $this->validate_type( $object_type );
		$post_id     = $this->validate_id( $post_id );

		$sql = $this->wpdb->prepare(
			"SELECT DISTINCT object_id FROM {$this->table}
             WHERE object_type = %s AND post_id = %d
             ORDER BY object_id ASC",
			$object_type,
			$post_id
		);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_col( $sql );
		return array_map( 'intval', $rows ?: array() );
	}

	/** @return int[] Object IDs of a type used in a specific block. */
	public function get_object_ids_for_block( int $post_id, string $block_id, string $object_type ): array {
		$post_id     = $this->validate_id( $post_id );
		$block_id    = $this->validate_block_id( $block_id );
		$object_type = $this->validate_type( $object_type );

		$sql = $this->wpdb->prepare(
			"SELECT object_id FROM {$this->table}
             WHERE post_id = %d AND block_id = %s AND object_type = %s
             ORDER BY object_id ASC",
			$post_id,
			$block_id,
			$object_type
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

	public function count_for_post( int $post_id, ?string $object_type = null ): int {
		$post_id = $this->validate_id( $post_id );

		if ( $object_type !== null ) {
			$object_type = $this->validate_type( $object_type );
			$sql         = $this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE post_id = %d AND object_type = %s",
				$post_id,
				$object_type
			);
		} else {
			$sql = $this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE post_id = %d",
				$post_id
			);
		}

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $this->wpdb->get_var( $sql );
	}

	// ---------------------------------------------------------------------
	// Bulk & sync
	// ---------------------------------------------------------------------

	/**
	 * Bulk attach many objects of the same type into a single block.
	 * Returns number of new rows inserted (duplicates ignored).
	 *
	 * @param int    $post_id
	 * @param string $block_id
	 * @param string $object_type 'card'|'note'
	 * @param int[]  $object_ids
	 */
	public function bulk_attach_objects_to_block( int $post_id, string $block_id, string $object_type, array $object_ids ): int {
		$post_id     = $this->validate_id( $post_id );
		$block_id    = $this->validate_block_id( $block_id );
		$object_type = $this->validate_type( $object_type );
		$object_ids  = $this->normalize_ids( $object_ids );
		if ( ! $object_ids ) {
			return 0;
		}

		$inserted = 0;
		foreach ( array_chunk( $object_ids, 200 ) as $chunk ) {
			$vals = array();
			$ph   = array();
			foreach ( $chunk as $oid ) {
				$vals[] = $object_type;
				$vals[] = $oid;
				$vals[] = $post_id;
				$vals[] = $block_id;
				$ph[]   = '(%s,%d,%d,%s)';
			}
			$sql = "INSERT IGNORE INTO {$this->table} (object_type, object_id, post_id, block_id) VALUES " . implode( ',', $ph );
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
	 * Sync a block to contain exactly the given set of object IDs for a type.
	 * Returns ['added'=>int,'removed'=>int,'kept'=>int].
	 */
	public function sync_block_objects( int $post_id, string $block_id, string $object_type, array $desired_object_ids ): array {
		$post_id     = $this->validate_id( $post_id );
		$block_id    = $this->validate_block_id( $block_id );
		$object_type = $this->validate_type( $object_type );
		$desired     = $this->normalize_ids( $desired_object_ids );

		$current   = $this->get_object_ids_for_block( $post_id, $block_id, $object_type );
		$to_add    = array_values( array_diff( $desired, $current ) );
		$to_remove = array_values( array_diff( $current, $desired ) );
		$kept      = count( $current ) - count( $to_remove );

		// Remove: DELETE ... WHERE post_id=? AND block_id=? AND object_type=? AND object_id IN (...)
		if ( $to_remove ) {
			foreach ( array_chunk( $to_remove, 500 ) as $chunk ) {
				$in  = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
				$sql = $this->wpdb->prepare(
					"DELETE FROM {$this->table}
                     WHERE post_id = %d AND block_id = %s AND object_type = %s AND object_id IN ($in)",
					$post_id,
					$block_id,
					$object_type,
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
		$added = $this->bulk_attach_objects_to_block( $post_id, $block_id, $object_type, $to_add );

		return array(
			'added'   => (int) $added,
			'removed' => (int) count( $to_remove ),
			'kept'    => (int) $kept,
		);
	}

	// ---------------------------------------------------------------------
	// Convenience clears
	// ---------------------------------------------------------------------

	/** Remove all usages of a given object across all posts/blocks. */
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

	/** Remove all usages within a post (any type / any block). */
	public function clear_post( int $post_id ): int {
		$post_id = $this->validate_id( $post_id );

		$res = $this->wpdb->delete( $this->table, array( 'post_id' => $post_id ), array( '%d' ) );
		if ( $res === false ) {
			throw new Exception( 'Clear post failed: ' . ( $this->wpdb->last_error ?: 'unknown DB error' ) );
		}
		return (int) $res;
	}

	/** Remove all usages for an object in a single post (any block). */
	public function detach_by_post( string $object_type, int $object_id, int $post_id ): int {
		$object_type = $this->validate_type( $object_type );
		$object_id   = $this->validate_id( $object_id );
		$post_id     = $this->validate_id( $post_id );

		$res = $this->wpdb->delete(
			$this->table,
			array(
				'object_type' => $object_type,
				'object_id'   => $object_id,
				'post_id'     => $post_id,
			),
			array( '%s', '%d', '%d' )
		);
		if ( $res === false ) {
			throw new Exception( 'Detach by post failed: ' . ( $this->wpdb->last_error ?: 'unknown DB error' ) );
		}
		return (int) $res;
	}

	/** Remove all usages in a specific block (optionally filter by type). */
	public function detach_block( int $post_id, string $block_id, ?string $object_type = null ): int {
		$post_id  = $this->validate_id( $post_id );
		$block_id = $this->validate_block_id( $block_id );

		$where   = array(
			'post_id'  => $post_id,
			'block_id' => $block_id,
		);
		$formats = array( '%d', '%s' );
		if ( $object_type !== null ) {
			$where['object_type'] = $this->validate_type( $object_type );
			$formats[]            = '%s';
		}

		$res = $this->wpdb->delete( $this->table, $where, $formats );
		if ( $res === false ) {
			throw new Exception( 'Detach block failed: ' . ( $this->wpdb->last_error ?: 'unknown DB error' ) );
		}
		return (int) $res;
	}

	// ---------------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------------

	protected function validate_type( string $type ): string {
		$type = strtolower( trim( $type ) );
		if ( ! isset( $this->allowedTypes[ $type ] ) ) {
			throw new Exception( 'Invalid object_type. Allowed: card, note.' );
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

	/**
	 * Basic block_id validation to prevent SQL injection & keep data clean.
	 * Allows letters, numbers, underscore, hyphen. Max 128 chars.
	 */
	protected function validate_block_id( string $block_id ): string {
		$block_id = trim( $block_id );
		if ( $block_id === '' || strlen( $block_id ) > 128 || ! preg_match( '/^[A-Za-z0-9_-]+$/', $block_id ) ) {
			throw new Exception( 'Invalid block_id.' );
		}
		return $block_id;
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
