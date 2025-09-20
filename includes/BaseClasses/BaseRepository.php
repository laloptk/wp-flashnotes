<?php

namespace WPFlashNotes\BaseClasses;

defined( 'ABSPATH' ) || exit;

use Exception;
use wpdb;

/**
 * BaseRepository
 *
 * Generic CRUD repository for a single custom table.
 * Child classes must provide: table name + sanitization rules.
 */
abstract class BaseRepository {

	/**
	 * WordPress database instance.
	 *
	 * @var wpdb
	 */
	protected wpdb $wpdb;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * Insert a new row.
	 *
	 * @param array $data Raw input; must pass sanitize_data().
	 * @return int Inserted ID.
	 * @throws Exception On validation or DB error.
	 */
	public function insert( array $data ): int {
		$sanitized = $this->sanitize_data( $data );

		if ( empty( $sanitized ) ) {
			throw new Exception(
				sprintf(
					'Insert aborted in %s: no valid fields provided.',
					$this->get_table_name()
				)
			);
		}

		$format = $this->build_format( $sanitized );
		$result = $this->wpdb->insert( $this->get_table_name(), $sanitized, $format );

		if ( $result === false ) {
			throw new Exception(
				sprintf(
					'Insert failed in %s: %s',
					$this->get_table_name(),
					$this->wpdb->last_error ?: 'unknown error'
				)
			);
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Read a single row by primary ID.
	 *
	 * @param int $id
	 * @return array|null Associative row or null if not found.
	 * @throws Exception If ID invalid.
	 */
	public function read( int $id ): ?array {
		$id  = $this->validate_id( $id );
		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->get_table_name()} WHERE id = %d LIMIT 1",
			$id
		);
		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return $row ?: null;
	}

	public function update( int $id, array $data ): bool {
		$id        = $this->validate_id( $id );
		$sanitized = $this->sanitize_data( $data );

		if ( ! $sanitized ) {
			return false;
		}

		// Fetch current DB row
		$current = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->get_table_name()} WHERE id = %d", $id ),
			ARRAY_A
		);

		if ( ! $current ) {
			throw new \Exception( "Row with ID {$id} not found in {$this->get_table_name()}" );
		}

		// Detect changes
		$changed = array();
		foreach ( $sanitized as $col => $val ) {
			$oldVal = array_key_exists( $col, $current ) ? $current[ $col ] : null;

			// Normalize to string for comparison
			$newVal = is_null( $val ) ? null : (string) $val;
			$oldVal = is_null( $oldVal ) ? null : (string) $oldVal;

			if ( $newVal !== $oldVal ) {
				$changed[ $col ] = $val;
			}
		}

		if ( empty( $changed ) ) {
			return false; // no update → updated_at stays the same
		}

		foreach ( $changed as $col => $val ) {
			$updateFormats[] = $this->fieldFormats()[ $col ] ?? '%s';
		}

		// Run update only with changed fields
		$result = $this->wpdb->update(
			$this->get_table_name(),
			$changed,
			array( 'id' => $id ),
			$updateFormats,
			array( '%d' )
		);

		if ( $result === false ) {
			throw new \Exception(
				sprintf(
					'Update failed in %s: %s',
					$this->get_table_name(),
					$this->wpdb->last_error ?: 'unknown error'
				)
			);
		}

		return $result > 0;
	}

	/**
	 * Hard delete a row by ID.
	 *
	 * @param int $id
	 * @return bool True if deleted; false if not found.
	 * @throws Exception On validation or DB error.
	 */
	public function delete( int $id ): bool {
		$id     = $this->validate_id( $id );
		$result = $this->wpdb->delete( $this->get_table_name(), array( 'id' => $id ), array( '%d' ) );

		if ( $result === false ) {
			throw new Exception(
				sprintf(
					'Delete failed in %s: %s',
					$this->get_table_name(),
					$this->wpdb->last_error ?: 'unknown error'
				)
			);
		}

		return $result > 0;
	}

	/**
	 * Soft delete by setting the soft-delete column (default: deleted_at).
	 *
	 * @param int $id
	 * @return bool True if updated; false if already soft-deleted or not found.
	 * @throws Exception On validation or DB error.
	 */
	public function soft_delete( int $id ): bool {
		$id  = $this->validate_id( $id );
		$col = $this->soft_delete_column();
		if ( $col === null ) {
			// Table does not support soft deletes.
			return $this->delete( $id );
		}

		$data = array(
			$col         => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);

		// Only include updated_at if table actually has it.
		if ( ! $this->column_exists( 'updated_at' ) ) {
			unset( $data['updated_at'] );
		}

		return $this->update( $id, $data );
	}

	/**
	 * Fetch multiple rows with a simple WHERE map and optional limit/offset.
	 * Not over-engineered: basic equals matches only; extend in child if needed.
	 *
	 * @param array    $where   e.g. ['user_id' => 123, 'is_mastered' => 1]
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return array<int, array>
	 */
	public function find( array $args = array() ): array {
		$table        = $this->get_table_name();
		$default_args = array(
			'where'  => array(),
			'search' => array(),
			'limit'  => null,
			'offset' => null,
		);

		$args = array_merge( $default_args, $args );

		$clauses        = array();
		$search_clauses = array();
		$values         = array();

		// Where clauses
		foreach ( $args['where'] as $col => $val ) {
			if ( ! $this->is_valid_identifier( $col ) ) {
				continue;
			}
			$placeholder = is_int( $val ) ? '%d' : '%s';
			$clauses[]   = "`{$col}` = {$placeholder}";
			$values[]    = $val;
		}

		// Search clauses: column => string
		foreach ( $args['search'] as $col => $str ) {
			if ( ! $this->is_valid_identifier( $col ) || $str === null ) {
				continue;
			}

			$search_clauses[] = "`{$col}` LIKE %s";
			$values[]         = '%' . $this->wpdb->esc_like( $str ) . '%';
		}

		// Build query
		$sql                = "SELECT * FROM {$table}";
		$clauses_str        = ! empty( $clauses ) ? implode( ' AND ', $clauses ) : '';
		$search_clauses_str = ! empty( $search_clauses ) ? '(' . implode( ' OR ', $search_clauses ) . ')' : '';
		$parts              = array_filter( array( $clauses_str, $search_clauses_str ) );
		if ( ! empty( $parts ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $parts );
		}

		// Pagination
		if ( $args['limit'] !== null ) {
			$sql .= $this->wpdb->prepare( ' LIMIT %d', $args['limit'] );
			if ( $args['offset'] !== null ) {
				$sql .= $this->wpdb->prepare( ' OFFSET %d', $args['offset'] );
			}
		}

		// Execute
		return $this->wpdb->get_results(
			$this->wpdb->prepare( $sql, ...$values ),
			ARRAY_A
		) ?: array();
	}

	public function get_by_column( string $column, $value, ?int $limit = null ) {
		// Simple safeguard: check column name exists in DB
		$columns = $this->wpdb->get_col( "SHOW COLUMNS FROM {$this->get_table_name()}", 0 );
		if ( ! in_array( $column, $columns, true ) ) {
			throw new Exception( "Invalid column name: {$column}" );
		}

		$placeholder = is_int( $value ) ? '%d' : '%s';

		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->get_table_name()} WHERE {$column} = {$placeholder}"
			. ( $limit ? " LIMIT {$limit}" : "" ),
			$value
		);

		if ( $limit === 1 ) {
			$row = $this->wpdb->get_row( $sql, ARRAY_A );
			return $row ?: null;
		}

		$rows = $this->wpdb->get_results( $sql, ARRAY_A );
		return $rows ?: [];
	}

	/**
	 * Child must return the fully-qualified table name (with prefix).
	 */
	abstract protected function get_table_name(): string;

	/**
	 * Child must sanitize and validate payloads.
	 * Must throw Exception on invalid data.
	 *
	 * @param array $data
	 * @return array Sanitized data (subset of $data).
	 * @throws Exception
	 */
	abstract protected function sanitize_data( array $data ): array;

	/**
	 * Optional: override to indicate the soft-delete column.
	 * Return null to disable soft deletes (fall back to hard delete).
	 *
	 * @return string|null
	 */
	protected function soft_delete_column(): ?string {
		// Your schemas use `deleted_at` on cards/notes; relation tables have no soft delete.
		return 'deleted_at';
	}

	/**
	 * Build wpdb format array from sanitized data.
	 * Defaults to %s, but allows overrides via fieldFormats() and a filter.
	 *
	 * @param array $data
	 * @return array
	 */
	protected function build_format( array $data ): array {
		$formats = $this->fieldFormats(); // e.g. ['id'=>'%d','user_id'=>'%d']
		$out     = array();
		foreach ( $data as $key => $val ) {
			$out[] = $formats[ $key ] ?? ( is_int( $val ) ? '%d' : '%s' );
		}

		/**
		 * Filter to override formats per table or globally.
		 *
		 * @param array  $out
		 * @param array  $data
		 * @param string $table
		 */
		return apply_filters( 'wpfn_repository_build_format', $out, $data, $this->get_table_name() );
	}

	/**
	 * Map of column => wpdb format (child override as needed).
	 * Example: return ['id'=>'%d','user_id'=>'%d','correct_count'=>'%d'];
	 *
	 * @return array<string,string>
	 */
	protected function fieldFormats(): array {
		return array();
	}

	/**
	 * Ensure a given ID is > 0.
	 *
	 * @param int $id
	 * @return int
	 * @throws Exception
	 */
	protected function validate_id( int $id ): int {
		$id = absint( $id );
		if ( $id <= 0 ) {
			throw new Exception( 'ID must be a positive integer.' );
		}
		return $id;
	}

	/**
	 * Lightweight identifier validation (columns, etc.).
	 */
	protected function is_valid_identifier( string $name ): bool {
		return (bool) preg_match( '/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $name );
	}

	/**
	 * Checks if a column exists in the table.
	 * Cheap and sufficient for deciding whether to set updated_at on soft delete.
	 */
	protected function column_exists( string $column ): bool {
		if ( ! $this->is_valid_identifier( $column ) ) {
			return false;
		}
		$table = $this->get_table_name();
		// Works on MySQL/MariaDB; fine for production. In Studio/SQLite this will just return false.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$row = $this->wpdb->get_row( "SHOW COLUMNS FROM `{$table}` LIKE '{$column}'" );
		return ! empty( $row );
	}
}
