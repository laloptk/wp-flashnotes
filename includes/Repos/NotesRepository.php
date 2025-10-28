<?php

namespace WPFlashNotes\Repos;

defined( 'ABSPATH' ) || exit;

use WPFlashNotes\BaseClasses\BaseRepository;
use WPFlashNotes\Errors\WPFlashNotesError;

/**
 * NotesRepository
 *
 * CRUD for the wpfn_notes table.
 * - Enforces non-empty title (when provided).
 * - Supports partial updates safely.
 * - Throws WPFlashNotesError on all validation/DB issues.
 */
class NotesRepository extends BaseRepository {

	protected function get_table_name(): string {
		return $this->wpdb->prefix . 'wpfn_notes';
	}

	/**
	 * Sanitize and validate a data payload for insert/update.
	 *
	 * @throws WPFlashNotesError On invalid field values.
	 */
	protected function sanitize_data( array $data ): array {
		$sanitized_data = array();

		foreach ( $data as $field_name => $field_value ) {
			switch ( $field_name ) {

				case 'user_id':
					$uid = (int) $field_value;
					if ( $uid <= 0 ) {
						$uid = (int) get_current_user_id();
					}
					if ( $uid <= 0 ) {
						throw new WPFlashNotesError(
							'validation',
							'user_id is required.',
							400
						);
					}
					$sanitized_data['user_id'] = $uid;
					break;

				case 'title':
					$title = trim( (string) $field_value );
					if ( $title === '' ) {
						throw new WPFlashNotesError(
							'validation',
							'Title cannot be empty.',
							400
						);
					}
					$sanitized_data['title'] = mb_substr( $title, 0, 255 );
					break;

				case 'block_id':
					if ( empty( $field_value ) ) {
						throw new WPFlashNotesError(
							'validation',
							'block_id is required for note records.',
							400
						);
					}
					$sanitized_data['block_id'] = sanitize_text_field( (string) $field_value );
					break;

				case 'content':
					$sanitized_data['content'] = wp_kses_post( (string) $field_value );
					break;

				case 'deleted_at':
				case 'created_at':
				case 'updated_at':
					$sanitized_data[ $field_name ] = self::normalize_datetime( $field_value );
					break;

				default:
					// Ignore unknown fields silently.
					break;
			}
		}

		return $sanitized_data;
	}

	/**
	 * Upsert a note row based on a block's attributes.
	 *
	 * @throws WPFlashNotesError
	 */
	public function upsert_from_block( array $block ): int {
		$attrs    = $block['attrs'] ?? array();
		$block_id = $attrs['block_id'] ?? $block['block_id'] ?? null;

		if ( empty( $block_id ) ) {
			throw new WPFlashNotesError(
				'validation',
				'Note block is missing block_id.',
				400
			);
		}

		$data = array(
			'title'    => $attrs['title'] ?? '',
			'block_id' => $block_id,
			'content'  => $attrs['content'] ?? '',
			'user_id'  => get_current_user_id(),
		);

		$existing = $this->get_by_block_id( $block_id );

		if ( ! empty( $existing ) ) {
			// get_by_block_id returns a single row or array of rows
			$row = is_array( $existing[0] ?? null ) ? $existing[0] : $existing;
			$this->update( (int) $row['id'], $data );
			return (int) $row['id'];
		}

		return $this->insert( $data );
	}

	public function get_by_block_id( string $block_id ): ?array {
		return $this->get_by_column( 'block_id', $block_id );
	}

	protected function fieldFormats(): array {
		return array(
			'id'         => '%d',
			'title'      => '%s',
			'block_id'   => '%s',
			'user_id'    => '%d',
			'content'    => '%s',
			'deleted_at' => '%s',
			'created_at' => '%s',
			'updated_at' => '%s',
		);
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
