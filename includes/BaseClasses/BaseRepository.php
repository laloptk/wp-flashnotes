<?php

namespace WPFlashNotes\BaseClasses;

defined( 'ABSPATH' ) || exit;

use wpdb;
use WPFlashNotes\Errors\WPFlashNotesError;

/**
 * BaseRepository
 *
 * Generic CRUD repository for a single custom table.
 * Child classes must provide: table name + sanitization rules.
 */
abstract class BaseRepository {

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
	 * @throws WPFlashNotesError On validation or DB error.
	 */
	public function insert( array $data ): int {
		$sanitized = $this->sanitize_data( $data );

		if ( empty( $sanitized ) ) {
			throw new WPFlashNotesError(
				'validation',
				sprintf(
					'Insert aborted in %s: no valid fields provided.',
					$this->get_table_name()
				),
				400
			);
		}

		$format = $this->build_format( $sanitized );
		$result = $this->wpdb->insert( $this->get_table_name(), $sanitized, $format );

		if ( $result === false ) {
			throw new WPFlashNotesError(
				'db',
				sprintf(
					'Insert failed in %s: %s',
					$this->get_table_name(),
					$this->wpdb->last_error ?: 'unknown error'
				),
				500
			);
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Read a single row by primary ID.
	 *
	 * @param int $id
	 * @return array|null Associative row or null if not found.
	 * @throws WPFlashNotesError If ID invalid.
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

	/**
	 * Update a row by ID.
	 *
	 * @param int $id
	 * @param array $data
	 * @return bool True if updated, false if no changes.
	 * @throws WPFlashNotesError On DB or validation error.
	 */
	public function update( int $id, array $data ): bool {
		$id        = $this->validate_id( $id );
		$sanitized = $this->sanitize_data( $data );

		if ( ! $sanitized ) {
			return false;
		}

		$current = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->get_table_name()} WHERE id = %d", $id ),
			ARRAY_A
		);

		if ( ! $current ) {
			throw new WPFlashNotesError(
				'not_found',
				sprintf( 'Row with ID %d not found in %s', $id, $this->get_table_name() ),
				404
			);
		}

		$changed        = array();
		$update_formats  = array();

		foreach ( $sanitized as $col => $val ) {
			$oldVal = array_key_exists( $col, $current ) ? $current[ $col ] : null;
			$newVal = is_null( $val ) ? null : (string) $val;
			$oldVal = is_null( $oldVal ) ? null : (string) $oldVal;

			if ( $newVal !== $oldVal ) {
				$changed[ $col ] = $val;
				$update_formats[] = $this->fieldFormats()[ $col ] ?? '%s';
			}
		}

		if ( empty( $changed ) ) {
			return false;
		}

		$result = $this->wpdb->update(
			$this->get_table_name(),
			$changed,
			array( 'id' => $id ),
			$update_formats,
			array( '%d' )
		);

		if ( $result === false ) {
			throw new WPFlashNotesError(
				'db',
				sprintf(
					'Update failed in %s: %s',
					$this->get_table_name(),
					$this->wpdb->last_error ?: 'unknown error'
				),
				500
			);
		}

		return $result > 0;
	}

	/**
	 * Delete a row by ID.
	 *
	 * @param int $id
	 * @return bool True if deleted; false if not found.
	 * @throws WPFlashNotesError On validation or DB error.
	 */
	public function delete( int $id ): bool {
		$id     = $this->validate_id( $id );
		$result = $this->wpdb->delete( $this->get_table_name(), array( 'id' => $id ), array( '%d' ) );

		if ( $result === false ) {
			throw new WPFlashNotesError(
				'db',
				sprintf(
					'Delete failed in %s: %s',
					$this->get_table_name(),
					$this->wpdb->last_error ?: 'unknown error'
				),
				500
			);
		}

		return $result > 0;
	}

	/**
	 * Fetch multiple rows with a simple WHERE/SEARCH map.
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

		foreach ( $args['where'] as $where_column => $where_value ) {
			if ( ! $this->column_exists( $where_column ) ) {
				continue;
			}
			$placeholder       = is_int( $where_value ) ? '%d' : '%s';
			$where_clauses[]   = "`{$where_column}` = {$placeholder}";
			$prepared_values[] = $where_value;
		}

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
		if ( ! $this->column_exists( $column ) ) {
			throw new WPFlashNotesError(
				'validation',
				sprintf( 'Invalid column name: %s', $column ?: 'empty column name' ),
				400
			);
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

	abstract protected function get_table_name(): string;
	abstract protected function sanitize_data( array $data ): array;

	protected function build_format( array $data ): array {
		$formats = $this->fieldFormats();
		$out     = array();
		foreach ( $data as $key => $val ) {
			$out[] = $formats[ $key ] ?? ( is_int( $val ) ? '%d' : '%s' );
		}
		return apply_filters( 'wpfn_repository_build_format', $out, $data, $this->get_table_name() );
	}

	protected function fieldFormats(): array {
		return array();
	}

	protected function validate_id( int $id ): int {
		$id = absint( $id );
		if ( $id <= 0 ) {
			throw new WPFlashNotesError( 'validation', 'ID must be a positive integer.', 400 );
		}
		return $id;
	}

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

	protected function column_exists( string $column ): bool {
		if ( ! $this->is_valid_identifier( $column ) ) {
			return false;
		}
		$columns = $this->get_table_columns();
		return in_array( $column, $columns, true );
	}
}
