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

	protected static array $table_cols_cache = array();

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

		$where_clauses   = array();
		$search_clauses  = array();
		$prepared_values = array();

		// WHERE clauses
		foreach ( $args['where'] as $where_column => $where_value ) {
			if ( ! $this->column_exists( $where_column ) ) {
				continue;
			}
			$placeholder       = is_int( $where_value ) ? '%d' : '%s';
			$where_clauses[]   = "`{$where_column}` = {$placeholder}";
			$prepared_values[] = $where_value;
		}

		// SEARCH clauses (LIKE)
		foreach ( $args['search'] as $search_column => $search_term ) {
			if ( ! $this->column_exists( $search_column ) || $search_term === null ) {
				continue;
			}
			$search_clauses[]  = "`{$search_column}` LIKE %s";
			$prepared_values[] = '%' . $this->wpdb->esc_like( (string) $search_term ) . '%';
		}

		$sql = "SELECT * FROM {$table}";

		$where_str  = ! empty( $where_clauses ) ? implode( ' AND ', $where_clauses ) : '';
		$search_str = ! empty( $search_clauses ) ? '(' . implode( ' OR ', $search_clauses ) . ')' : '';

		$all_filters = array_filter( array( $where_str, $search_str ) );
		if ( ! empty( $all_filters ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $all_filters );
		}

		// Pagination
		if ( $args['limit'] !== null ) {
			$sql .= $this->wpdb->prepare( ' LIMIT %d', (int) $args['limit'] );
			if ( $args['offset'] !== null ) {
				$sql .= $this->wpdb->prepare( ' OFFSET %d', (int) $args['offset'] );
			}
		}

		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare( $sql, ...$prepared_values ),
			ARRAY_A
		);

		return $rows ?: array();
	}


	public function get_by_column( string $column, $value, ?int $limit = null ) {
		// Simple safeguard: check column name exists in DB
		if ( ! $this->column_exists( $column ) ) {
			throw new Exception( "Invalid column name: {$column}" );
		}

		$placeholder = is_int( $value ) ? '%d' : '%s';

		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->get_table_name()} WHERE {$column} = {$placeholder}"
			. ( $limit ? " LIMIT {$limit}" : '' ),
			$value
		);

		if ( $limit === 1 ) {
			$row = $this->wpdb->get_row( $sql, ARRAY_A );
			return $row ?: null;
		}

		$rows = $this->wpdb->get_results( $sql, ARRAY_A );
		return $rows ?: array();
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


	protected function get_table_columns(): array {
		$table = $this->get_table_name();

		if ( ! isset( self::$table_cols_cache[ $table ] ) ) {
			$columns                          = $this->wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );
			self::$table_cols_cache[ $table ] = is_array( $columns ) ? $columns : array();
		}

		return self::$table_cols_cache[ $table ];
	}

	/**
	 * Checks if a column exists in the table.
	 * Cheap and sufficient for deciding whether to set updated_at on soft delete.
	 */
	protected function column_exists( string $column ): bool {
		if ( ! $this->is_valid_identifier( $column ) ) {
			return false;
		}
		$columns = $this->get_table_columns();
		return in_array( $column, $columns, true );
	}
}
