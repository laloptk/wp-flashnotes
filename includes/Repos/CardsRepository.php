<?php

namespace WPFlashNotes\Repos;

defined( 'ABSPATH' ) || exit;

use WPFlashNotes\BaseClasses\BaseRepository;
use WPFlashNotes\Errors\WPFlashNotesError;

/**
 * CardsRepository
 *
 * CRUD for the wpfn_cards table (flexible card types).
 * - Supports partial updates (sanitize_data() only validates provided fields).
 * - Stores answers and right answers as normalized JSON strings (LONGTEXT).
 * - Includes a helper to record review results (simple SM-2-ish).
 */
class CardsRepository extends BaseRepository {

	protected function get_table_name(): string {
		return $this->wpdb->prefix . 'wpfn_cards';
	}

	/**
	 * Sanitize and validate a data payload for insert/update.
	 * Only fields present in $data are processed (safe for partial updates).
	 *
	 * @throws WPFlashNotesError On invalid field values.
	 */
	protected function sanitize_data( array $data ): array {
		$sanitized = array();

		foreach ( $data as $field => $value ) {
			switch ( $field ) {

				case 'block_id':
					if ( empty( $value ) ) {
						throw new WPFlashNotesError(
							'validation',
							'block_id is required for cards.',
							400
						);
					}
					$sanitized['block_id'] = sanitize_text_field( (string) $value );
					break;

				case 'question':
					$question = wp_kses_post( (string) $value );
					if ( $question === '' ) {
						throw new WPFlashNotesError(
							'validation',
							'Question cannot be empty.',
							400
						);
					}
					$sanitized['question'] = $question;
					break;

				case 'answers':
					$sanitized['answers'] = self::normalize_json_field( $value );
					break;

				case 'right_answers':
					$sanitized['right_answers'] = self::normalize_json_field( $value );
					break;

				case 'explanation':
					$sanitized['explanation'] = $value === null ? null : wp_kses_post( (string) $value );
					break;

				case 'user_id':
					$sanitized['user_id'] = intval( $value );
					break;

				case 'card_type':
					$allowed_types = array( 'flip', 'true-false', 'multiple-choice', 'multiple-select', 'fill-in-blank' );
					if ( ! in_array( $value, $allowed_types, true ) ) {
						throw new WPFlashNotesError(
							'validation',
							sprintf( 'Invalid card_type: %s', $value ),
							400
						);
					}
					$sanitized['card_type'] = $value;
					break;

				case 'status':
					$allowed_status = array( 'active', 'orphan' );
					if ( ! in_array( $value, $allowed_status, true ) ) {
						throw new WPFlashNotesError(
							'validation',
							sprintf( 'Invalid status: %s', $value ),
							400
						);
					}
					$sanitized['status'] = $value;
					break;

				case 'last_seen':
				case 'next_due':
				case 'deleted_at':
					$sanitized[ $field ] = $value
						? gmdate( 'Y-m-d H:i:s', strtotime( (string) $value ) )
						: null;
					break;

				case 'correct_count':
				case 'incorrect_count':
				case 'streak':
					$sanitized[ $field ] = max( 0, intval( $value ) );
					break;

				case 'ease_factor':
					$sanitized['ease_factor'] = is_numeric( $value )
						? number_format( (float) $value, 2, '.', '' )
						: 2.50;
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
			'answers'       	 => '%s',
			'user_id'            => '%d',
			'right_answers' 	 => '%s',
			'explanation'        => '%s',
			'card_type'          => '%s',
			'status'             => '%s',
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
	 * @throws WPFlashNotesError
	 */
	public function upsert_from_block( array $block ): int {
		$attrs   = $block['attrs'] ?? array();
		$block_id = $attrs['block_id'] ?? $block['block_id'] ?? null;
		$card_type = $block['card_type'] ?? '';

		if ( empty( $block_id ) ) {
			throw new WPFlashNotesError(
				'validation',
				'Card block is missing block_id.',
				400
			);
		}

		error_log("This comes from the CardsRepository attrs: " . json_encode($attrs));

		$data = array(
			'block_id'           => $block_id,
			'question'           => $attrs['question'] ?? '',
			'answers'            => $attrs['answers'] ?? '[]',
			'right_answers'      => $attrs['right_answers'] ?? '[]',
			'explanation'        => $attrs['explanation'] ?? null,
			'card_type'			 => $card_type,
			'user_id'            => get_current_user_id(),
		);

		$existing = $this->get_by_block_id( $block_id );

		if ( $existing ) {
			$this->update( (int) $existing['id'], $data );
			return (int) $existing['id'];
		}

		return $this->insert( $data );
	}

	public function get_by_block_id( string $block_id ): ?array {
		return $this->get_by_column( 'block_id', $block_id, 1 );
	}

	private static function normalize_json_field( $value ): ?string {
		if ( $value === null || $value === '' ) {
			return null;
		}

		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
				return wp_json_encode( array_values( array_map( 'strval', $decoded ) ) );
			}
			return wp_json_encode( array( (string) $value ) );
		}

		if ( is_array( $value ) ) {
			return wp_json_encode( array_values( array_map( 'strval', $value ) ) );
		}

		return wp_json_encode( array( (string) $value ) );
	}

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
}
