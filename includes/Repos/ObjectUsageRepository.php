<?php

namespace WPFlashNotes\Repos;

defined( 'ABSPATH' ) || exit;

use Exception;
use wpdb;

/**
 * ObjectUsageRepository
 *
 * Manages object usage records in {prefix}wpfn_object_usage.
 * Composite PK: (object_type, object_id, post_id, block_id).
 */
class ObjectUsageRepository {

	protected wpdb $db;
	protected string $table;

	/** @var array<string,bool> */
	protected array $allowed_types = [
		'card'     => true,
		'note'     => true,
		'inserter' => true,
	];

	public function __construct() {
		global $wpdb;
		$this->db    = $wpdb;
		$this->table = $wpdb->prefix . 'wpfn_object_usage';
	}

	/**
	 * Attach usage idempotently.
	 */
	public function attach( string $object_type, int $object_id, int $post_id, string $block_id ): bool {
		$object_type = $this->validate_type( $object_type );
		$object_id   = $this->validate_id( $object_id );
		$post_id     = $this->validate_id( $post_id );
		$block_id    = $this->validate_block_id( $block_id );

		$insert_sql = $this->db->prepare(
			"INSERT IGNORE INTO {$this->table} (object_type, object_id, post_id, block_id) VALUES (%s, %d, %d, %s)",
			$object_type,
			$object_id,
			$post_id,
			$block_id
		);

		$query_result = $this->db->query( $insert_sql );
		if ( $query_result === false ) {
			throw new Exception( 'Attach failed: ' . ( $this->db->last_error ?: 'unknown DB error' ) );
		}
		return $query_result >= 0;
	}

	/**
	 * Detach usage.
	 */
	public function detach( string $object_type, int $object_id, int $post_id, string $block_id ): bool {
		$object_type = $this->validate_type( $object_type );
		$object_id   = $this->validate_id( $object_id );
		$post_id     = $this->validate_id( $post_id );
		$block_id    = $this->validate_block_id( $block_id );

		$delete_result = $this->db->delete(
			$this->table,
			[
				'object_type' => $object_type,
				'object_id'   => $object_id,
				'post_id'     => $post_id,
				'block_id'    => $block_id,
			],
			[ '%s', '%d', '%d', '%s' ]
		);
		if ( $delete_result === false ) {
			throw new Exception( 'Detach failed: ' . ( $this->db->last_error ?: 'unknown DB error' ) );
		}
		return $delete_result > 0;
	}

	/**
	 * Check if usage exists.
	 */
	public function exists( string $object_type, int $object_id, int $post_id, string $block_id ): bool {
		$object_type = $this->validate_type( $object_type );
		$object_id   = $this->validate_id( $object_id );
		$post_id     = $this->validate_id( $post_id );
		$block_id    = $this->validate_block_id( $block_id );

		$select_sql = $this->db->prepare(
			"SELECT 1 FROM {$this->table}
             WHERE object_type = %s AND object_id = %d AND post_id = %d AND block_id = %s
             LIMIT 1",
			$object_type,
			$object_id,
			$post_id,
			$block_id
		);

		return (bool) $this->db->get_var( $select_sql );
	}

	/**
	 * Get block IDs for an object in a post.
	 */
	public function get_block_ids_for_object_in_post( string $object_type, int $object_id, int $post_id ): array {
		$object_type = $this->validate_type( $object_type );
		$object_id   = $this->validate_id( $object_id );
		$post_id     = $this->validate_id( $post_id );

		$select_sql = $this->db->prepare(
			"SELECT block_id FROM {$this->table}
             WHERE object_type = %s AND object_id = %d AND post_id = %d
             ORDER BY block_id ASC",
			$object_type,
			$object_id,
			$post_id
		);

		$result_rows = $this->db->get_col( $select_sql ) ?: [];
		return array_values( array_map( 'strval', $result_rows ) );
	}

	/**
	 * Get post IDs where an object is used.
	 */
	public function get_post_ids_for_object( string $object_type, int $object_id ): array {
		$object_type = $this->validate_type( $object_type );
		$object_id   = $this->validate_id( $object_id );

		$select_sql = $this->db->prepare(
			"SELECT DISTINCT post_id FROM {$this->table}
             WHERE object_type = %s AND object_id = %d
             ORDER BY post_id ASC",
			$object_type,
			$object_id
		);
		$result_rows = $this->db->get_col( $select_sql ) ?: [];
		return array_map( 'intval', $result_rows );
	}

	/**
	 * Get object IDs of a type in a post.
	 */
	public function get_object_ids_for_post( string $object_type, int $post_id ): array {
		$object_type = $this->validate_type( $object_type );
		$post_id     = $this->validate_id( $post_id );

		$select_sql = $this->db->prepare(
			"SELECT DISTINCT object_id FROM {$this->table}
             WHERE object_type = %s AND post_id = %d
             ORDER BY object_id ASC",
			$object_type,
			$post_id
		);

		$result_rows = $this->db->get_col( $select_sql ) ?: [];
		return array_map( 'intval', $result_rows );
	}

	/**
	 * Get object IDs in a block.
	 */
	public function get_object_ids_for_block( int $post_id, string $block_id, string $object_type ): array {
		$post_id     = $this->validate_id( $post_id );
		$block_id    = $this->validate_block_id( $block_id );
		$object_type = $this->validate_type( $object_type );

		$select_sql = $this->db->prepare(
			"SELECT object_id FROM {$this->table}
             WHERE post_id = %d AND block_id = %s AND object_type = %s
             ORDER BY object_id ASC",
			$post_id,
			$block_id,
			$object_type
		);

		$result_rows = $this->db->get_col( $select_sql ) ?: [];
		return array_map( 'intval', $result_rows );
	}

	/**
	 * Count usages for an object.
	 */
	public function count_for_object( string $object_type, int $object_id ): int {
		$object_type = $this->validate_type( $object_type );
		$object_id   = $this->validate_id( $object_id );

		$count_sql = $this->db->prepare(
			"SELECT COUNT(*) FROM {$this->table} WHERE object_type = %s AND object_id = %d",
			$object_type,
			$object_id
		);

		return (int) $this->db->get_var( $count_sql );
	}

	/**
	 * Count usages for a post (optionally filter by type).
	 */
	public function count_for_post( int $post_id, ?string $object_type = null ): int {
		$post_id = $this->validate_id( $post_id );

		if ( $object_type !== null ) {
			$object_type = $this->validate_type( $object_type );
			$count_sql   = $this->db->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE post_id = %d AND object_type = %s",
				$post_id,
				$object_type
			);
		} else {
			$count_sql = $this->db->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE post_id = %d",
				$post_id
			);
		}

		return (int) $this->db->get_var( $count_sql );
	}

	/**
	 * Bulk attach objects to a block.
	 */
	public function bulk_attach_objects_to_block( int $post_id, string $block_id, string $object_type, array $object_ids ): int {
		$post_id     = $this->validate_id( $post_id );
		$block_id    = $this->validate_block_id( $block_id );
		$object_type = $this->validate_type( $object_type );
		$object_ids  = $this->normalize_ids( $object_ids );

		if ( ! $object_ids ) {
			return 0;
		}

		$total_inserted = 0;
		foreach ( array_chunk( $object_ids, 200 ) as $object_chunk ) {
			$values      = [];
			$placeholders = [];
			foreach ( $object_chunk as $object_id ) {
				$values[] = $object_type;
				$values[] = $object_id;
				$values[] = $post_id;
				$values[] = $block_id;
				$placeholders[] = '(%s,%d,%d,%s)';
			}
			$insert_sql   = "INSERT IGNORE INTO {$this->table} (object_type, object_id, post_id, block_id) VALUES " . implode( ',', $placeholders );
			$query_result = $this->db->query( $this->db->prepare( $insert_sql, ...$values ) );
			if ( $query_result === false ) {
				throw new Exception( 'Bulk attach failed: ' . ( $this->db->last_error ?: 'unknown DB error' ) );
			}
			$total_inserted += (int) $query_result;
		}
		return $total_inserted;
	}

	/**
	 * Sync block objects with desired set.
	 */
	public function sync_block_objects( int $post_id, string $block_id, string $object_type, array $desired_object_ids ): array {
		$post_id     = $this->validate_id( $post_id );
		$block_id    = $this->validate_block_id( $block_id );
		$object_type = $this->validate_type( $object_type );
		$desired     = $this->normalize_ids( $desired_object_ids );

		$current_ids = $this->get_object_ids_for_block( $post_id, $block_id, $object_type );
		$to_add      = array_values( array_diff( $desired, $current_ids ) );
		$to_remove   = array_values( array_diff( $current_ids, $desired ) );
		$kept_count  = count( $current_ids ) - count( $to_remove );

		if ( $to_remove ) {
			foreach ( array_chunk( $to_remove, 500 ) as $remove_chunk ) {
				$placeholders = implode( ',', array_fill( 0, count( $remove_chunk ), '%d' ) );
				$delete_sql   = $this->db->prepare(
					"DELETE FROM {$this->table}
                     WHERE post_id = %d AND block_id = %s AND object_type = %s AND object_id IN ($placeholders)",
					$post_id,
					$block_id,
					$object_type,
					...$remove_chunk
				);
				$delete_result = $this->db->query( $delete_sql );
				if ( $delete_result === false ) {
					throw new Exception( 'Sync remove failed: ' . ( $this->db->last_error ?: 'unknown DB error' ) );
				}
			}
		}

		$added_count = $this->bulk_attach_objects_to_block( $post_id, $block_id, $object_type, $to_add );

		return [
			'added'   => (int) $added_count,
			'removed' => (int) count( $to_remove ),
			'kept'    => (int) $kept_count,
		];
	}

	/**
	 * Clear all usages of an object.
	 */
	public function clear_object( string $object_type, int $object_id ): int {
		$object_type = $this->validate_type( $object_type );
		$object_id   = $this->validate_id( $object_id );

		$delete_result = $this->db->delete(
			$this->table,
			[ 'object_type' => $object_type, 'object_id' => $object_id ],
			[ '%s', '%d' ]
		);
		if ( $delete_result === false ) {
			throw new Exception( 'Clear object failed: ' . ( $this->db->last_error ?: 'unknown DB error' ) );
		}
		return (int) $delete_result;
	}

	/**
	 * Clear all usages for a post.
	 */
	public function clear_post( int $post_id ): int {
		$post_id = $this->validate_id( $post_id );

		$delete_result = $this->db->delete( $this->table, [ 'post_id' => $post_id ], [ '%d' ] );
		if ( $delete_result === false ) {
			throw new Exception( 'Clear post failed: ' . ( $this->db->last_error ?: 'unknown DB error' ) );
		}
		return (int) $delete_result;
	}

	/**
	 * Detach object usages by post.
	 */
	public function detach_by_post( string $object_type, int $object_id, int $post_id ): int {
		$object_type = $this->validate_type( $object_type );
		$object_id   = $this->validate_id( $object_id );
		$post_id     = $this->validate_id( $post_id );

		$delete_result = $this->db->delete(
			$this->table,
			[ 'object_type' => $object_type, 'object_id' => $object_id, 'post_id' => $post_id ],
			[ '%s', '%d', '%d' ]
		);
		if ( $delete_result === false ) {
			throw new Exception( 'Detach by post failed: ' . ( $this->db->last_error ?: 'unknown DB error' ) );
		}
		return (int) $delete_result;
	}

	/**
	 * Detach usages in a block (optionally by type).
	 */
	public function detach_block( int $post_id, string $block_id, ?string $object_type = null ): int {
		$post_id  = $this->validate_id( $post_id );
		$block_id = $this->validate_block_id( $block_id );

		$where   = [ 'post_id' => $post_id, 'block_id' => $block_id ];
		$formats = [ '%d', '%s' ];

		if ( $object_type !== null ) {
			$where['object_type'] = $this->validate_type( $object_type );
			$formats[]            = '%s';
		}

		$delete_result = $this->db->delete( $this->table, $where, $formats );
		if ( $delete_result === false ) {
			throw new Exception( 'Detach block failed: ' . ( $this->db->last_error ?: 'unknown DB error' ) );
		}
		return (int) $delete_result;
	}

	/**
	 * Validate object_type.
	 */
	protected function validate_type( string $type ): string {
		$type = strtolower( trim( $type ) );
		if ( ! isset( $this->allowed_types[ $type ] ) ) {
			throw new Exception( 'Invalid object_type.' );
		}
		return $type;
	}

	/**
	 * Validate ID is positive integer.
	 */
	protected function validate_id( int $id ): int {
		$validated_id = absint( $id );
		if ( $validated_id <= 0 ) {
			throw new Exception( 'ID must be positive.' );
		}
		return $validated_id;
	}

	/**
	 * Validate block_id format.
	 */
	protected function validate_block_id( string $block_id ): string {
		$block_id = trim( $block_id );
		if ( $block_id === '' || strlen( $block_id ) > 128 || ! preg_match( '/^[A-Za-z0-9_-]+$/', $block_id ) ) {
			throw new Exception( 'Invalid block_id.' );
		}
		return $block_id;
	}

	/**
	 * Normalize ID array.
	 */
	protected function normalize_ids( array $ids ): array {
		$normalized = [];
		foreach ( $ids as $raw_id ) {
			$validated_id = absint( $raw_id );
			if ( $validated_id > 0 ) {
				$normalized[ $validated_id ] = true;
			}
		}
		$list = array_keys( $normalized );
		sort( $list, SORT_NUMERIC );
		return $list;
	}
}

