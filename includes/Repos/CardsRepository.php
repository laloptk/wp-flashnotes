<?php

namespace WPFlashNotes\Repos;

defined( 'ABSPATH' ) || exit;

use WPFlashNotes\BaseClasses\BaseRepository;

/**
 * CardsRepository
 *
 * CRUD for the wpfn_cards table (flexible card types).
 * - Supports partial updates (sanitize_data() only validates provided fields).
 * - Stores answers and right answers as normalized JSON strings (LONGTEXT).
 * - Includes a helper to record review results (simple SM-2-ish).
 */
class CardsRepository extends BaseRepository {

	/**
	 * Allowed card types (must match ENUM in schema).
	 *
	 * @var array<int,string>
	 */
	private const CARD_TYPES = array(
		'flip',
		'true_false',
		'multiple_choice',
		'multiple_select',
		'fill_in_blank',
	);

	private const CARD_STATUS = array(
		'active',
		'orphan',
	);

	/**
	 * Fully-qualified table name.
	 */
	protected function get_table_name(): string {
		return $this->wpdb->prefix . 'wpfn_cards';
	}

	/**
	 * Sanitize and validate a data payload for insert/update.
	 * Only fields present in $data are processed (safe for partial updates).
	 *
	 * @param array $data
	 * @return array Sanitized subset of $data, ready for wpdb insert/update.
	 * @throws \Exception On invalid field values.
	 */
	protected function sanitize_data( array $data ): array {
		$sanitized = array();

		foreach ( $data as $field => $value ) {
			switch ( $field ) {
				case 'block_id':
					$sanitized['block_id'] = $value === null ? null : sanitize_text_field( (string) $value );
					break;

				case 'question':
					$question = wp_kses_post( (string) $value );
					if ( $question === '' ) {
						throw new \Exception( 'Question cannot be empty.' );
					}
					$sanitized['question'] = $question;
					break;

				case 'answers_json':
					$sanitized['answers_json'] = self::normalize_json_field( $value );
					break;

				case 'right_answers_json':
					$sanitized['right_answers_json'] = self::normalize_json_field( $value );
					break;

				case 'explanation':
					$sanitized['explanation'] = $value === null ? null : wp_kses_post( (string) $value );
					break;

				case 'user_id':
					$sanitized['user_id'] = intval( $value );
					break;

				case 'card_type':
					$allowed_types = array( 'flip', 'true_false', 'multiple_choice', 'multiple_select', 'fill_in_blank' );
					$sanitized['card_type'] = in_array( $value, $allowed_types, true ) ? $value : 'flip';
					break;
				case 'status': 
					$allowed_status = array( 'active', 'orphan' );
					$sanitized['status'] = in_array( $value, $allowed_status ) ? $value : 'active';
					break;
				case 'last_seen':
				case 'next_due':
				case 'deleted_at':
					$sanitized[ $field ] = $value ? gmdate( 'Y-m-d H:i:s', strtotime( (string) $value ) ) : null;
					break;

				case 'correct_count':
				case 'incorrect_count':
				case 'streak':
					$sanitized[ $field ] = max( 0, intval( $value ) );
					break;

				case 'ease_factor':
					$sanitized['ease_factor'] = is_numeric( $value ) ? number_format( (float) $value, 2, '.', '' ) : 2.50;
					break;

				case 'is_mastered':
					$sanitized['is_mastered'] = $value ? 1 : 0;
					break;
			}
		}

		return $sanitized;
	}


	protected function fieldFormats(): array {
		return array(
			'block_id'           => '%s',
			'question'           => '%s',
			'answers_json'       => '%s',
			'user_id'            => '%d',
			'right_answers_json' => '%s',
			'explanation'        => '%s',
			'card_type'          => '%s',
			'status'			 => '%s',
			'last_seen'          => '%s',
			'next_due'           => '%s',
			'correct_count'      => '%d',
			'incorrect_count'    => '%d',
			'streak'             => '%d',
			'ease_factor'        => '%f',
			'is_mastered'        => '%d',
			'deleted_at'         => '%s',
		);
	}


	/**
	 * Upsert a card row based on a block's attributes.
	 *
	 * @param array $block Parsed block array (from BlockParser).
	 * @return int Card ID (row id in wpfn_cards).
	 */
	public function upsert_from_block( array $block ): int {
		$attrs   = $block['attrs'] ?? array();
		$blockId = $block['block_id'] ?? null;

		if ( ! $blockId ) {
			throw new \Exception( 'Card block is missing block_id.' );
		}

		$data = array(
			'block_id'           => $blockId,
			'question'           => $attrs['question'] ?? '',
			'answers_json'       => $attrs['answers_json'] ?? '[]',
			'right_answers_json' => $attrs['right_answers_json'] ?? '[]',
			'explanation'        => $attrs['explanation'] ?? null,
			'user_id'            => get_current_user_id(),
		);

		$existing = $this->get_by_block_id( $blockId );

		if ( $existing ) {
			$this->update( (int) $existing['id'], $data );
			return (int) $existing['id'];
		}

		return $this->insert( $data );
	}

	/**
	 * Lookup a card by block_id.
	 */
	public function get_by_block_id( string $block_id ): ?array {
		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->get_table_name()} WHERE block_id = %s LIMIT 1",
			$block_id
		);
		$row = $this->wpdb->get_row( $sql, ARRAY_A );
		return $row ?: null;
	}

	/**
	 * Normalize array|string|null into a compact JSON array string (or NULL).
	 * Ensures a dense string array (["...","..."]).
	 *
	 * @param mixed $value
	 * @return string|null
	 */
	private static function normalize_json_field( $value ): ?string {
		if ( $value === null || $value === '' ) {
			return null;
		}

		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
				return wp_json_encode( array_values( array_map( 'strval', $decoded ) ) );
			}
			// Treat raw string as single-item array.
			return wp_json_encode( array( (string) $value ) );
		}

		if ( is_array( $value ) ) {
			return wp_json_encode( array_values( array_map( 'strval', $value ) ) );
		}

		return wp_json_encode( array( (string) $value ) );
	}

	/**
	 * Normalize a datetime-ish input (timestamp/int|string) to "Y-m-d H:i:s" GMT or NULL.
	 *
	 * @param mixed $value
	 * @return string|null
	 */
	private static function normalize_datetime( $value ): ?string {
		if ( $value === null || $value === '' ) {
			return null;
		}

		$timestamp = is_numeric( $value ) ? (int) $value : strtotime( (string) $value );
		if ( ! $timestamp ) {
			return null;
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Convenience: record a review result and update scheduling fields.
	 * Implements a light SM-2 style adjustment.
	 *
	 * @param int $id       Card ID.
	 * @param int $quality  0..5 (>=3 counts as correct).
	 * @return bool True if the row was updated.
	 */
	public function record_review( int $id, int $quality ): bool {
		$id      = $this->validate_id( $id );
		$quality = max( 0, min( 5, $quality ) );

		$row = $this->read( $id );
		if ( ! $row ) {
			return false;
		}

		$now_gmt = current_time( 'mysql', 1 );

		$correct_count   = (int) ( $row['correct_count'] ?? 0 );
		$incorrect_count = (int) ( $row['incorrect_count'] ?? 0 );
		$streak          = (int) ( $row['streak'] ?? 0 );
		$ease_factor     = isset( $row['ease_factor'] ) ? (float) $row['ease_factor'] : 2.50;

		if ( $quality >= 3 ) {
			++$correct_count;
			++$streak;
			// SM-2-ish ease update
			$ease_factor = max( 1.30, $ease_factor + ( 0.1 - ( 5 - $quality ) * ( 0.08 + ( 5 - $quality ) * 0.02 ) ) );
		} else {
			++$incorrect_count;
			$streak      = 0;
			$ease_factor = max( 1.30, $ease_factor - 0.2 );
		}

		// Simple spacing heuristic (tune later).
		$days_until   = $streak <= 1 ? 1 : ( $streak === 2 ? 3 : (int) round( ( $streak - 1 ) * $ease_factor ) );
		$next_due_gmt = gmdate( 'Y-m-d H:i:s', time() + $days_until * DAY_IN_SECONDS );

		return $this->update(
			$id,
			array(
				'last_seen'       => $now_gmt,
				'next_due'        => $next_due_gmt,
				'correct_count'   => $correct_count,
				'incorrect_count' => $incorrect_count,
				'streak'          => $streak,
				'ease_factor'     => $ease_factor,
				'updated_at'      => $now_gmt,
			)
		);
	}
}
