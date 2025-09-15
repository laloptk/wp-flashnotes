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
	 * Get relationships for an object type in a post (optionally within a block).
	 *
	 * @param string      $object_type  Object type (card|note).
	 * @param int         $post_id      Post ID.
	 * @param string|null $block_id     Optional block ID to filter.
	 * @return int[]                   List of object IDs.
	 * @throws Exception
	 */
	public function get_relationships( string $object_type, int $post_id, ?string $block_id = null ): array {
		$object_type = $this->validate_type( $object_type );
		$post_id     = $this->validate_id( $post_id );

		if ( $block_id !== null ) {
			$block_id = $this->validate_block_id( $block_id );
			$sql      = $this->db->prepare(
				"SELECT object_id FROM {$this->table}
				WHERE object_type = %s AND post_id = %d AND block_id = %s
				ORDER BY object_id ASC",
				$object_type,
				$post_id,
				$block_id
			);
		} else {
			$sql = $this->db->prepare(
				"SELECT DISTINCT object_id FROM {$this->table}
				WHERE object_type = %s AND post_id = %d
				ORDER BY object_id ASC",
				$object_type,
				$post_id
			);
		}

		$result_rows = $this->db->get_col( $sql ) ?: [];
		return array_map( 'intval', $result_rows );
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
			$values       = [];
			$placeholders = [];
			foreach ( $object_chunk as $object_id ) {
				$values[]       = $object_type;
				$values[]       = $object_id;
				$values[]       = $post_id;
				$values[]       = $block_id;
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

		// Use unified relationships method
		$current_ids = $this->get_relationships( $object_type, $post_id, $block_id );

		$to_add     = array_values( array_diff( $desired, $current_ids ) );
		$to_remove  = array_values( array_diff( $current_ids, $desired ) );
		$kept_count = count( $current_ids ) - count( $to_remove );

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
