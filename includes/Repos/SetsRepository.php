<?php

namespace WPFlashNotes\Repos;

defined( 'ABSPATH' ) || exit;

use WPFlashNotes\BaseClasses\BaseRepository;
use WPFlashNotes\Errors\WPFlashNotesError;

/**
 * SetsRepository
 *
 * CRUD + helpers for the {prefix}wpfn_sets table.
 *
 * Schema recap:
 *  - id BIGINT UNSIGNED PK AI
 *  - title VARCHAR(255) NOT NULL
 *  - post_id BIGINT UNSIGNED NULL
 *  - set_post_id BIGINT UNSIGNED NOT NULL UNIQUE (FK to wp_posts.ID for Study Set CPT)
 *  - user_id BIGINT UNSIGNED NOT NULL (FK to wp_users.ID)
 *  - created_at DATETIME DEFAULT CURRENT_TIMESTAMP
 *  - updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
 */
class SetsRepository extends BaseRepository {

	protected string $table;

	public function __construct() {
		parent::__construct();
		$this->table = $this->wpdb->prefix . 'wpfn_sets';
	}

	protected function get_table_name(): string {
		return $this->table;
	}

	protected function fieldFormats(): array {
		return array(
			'title'       => '%s',
			'post_id'     => '%d',
			'set_post_id' => '%d',
			'user_id'     => '%d',
		);
	}

	/**
	 * Sanitize & validate payload.
	 *
	 * @throws WPFlashNotesError
	 */
	protected function sanitize_data( array $data ): array {
		$out = array();

		if ( array_key_exists( 'title', $data ) ) {
			$title = trim( wp_strip_all_tags( (string) $data['title'] ) );
			if ( $title === '' ) {
				throw new WPFlashNotesError(
					'validation',
					'Title cannot be empty.',
					400
				);
			}
			$out['title'] = $title;
		}

		if ( array_key_exists( 'post_id', $data ) ) {
			$post_id = absint( $data['post_id'] );
			if ( $post_id > 0 ) {
				$out['post_id'] = $post_id;
			}
		}

		if ( array_key_exists( 'set_post_id', $data ) ) {
			$set_post_id = absint( $data['set_post_id'] );
			if ( $set_post_id <= 0 ) {
				throw new WPFlashNotesError(
					'validation',
					'set_post_id must be a positive integer.',
					400
				);
			}
			$out['set_post_id'] = $set_post_id;
		}

		if ( array_key_exists( 'user_id', $data ) ) {
			$uid = absint( $data['user_id'] );
			if ( $uid <= 0 ) {
				throw new WPFlashNotesError(
					'validation',
					'user_id must be a positive integer.',
					400
				);
			}
			$out['user_id'] = $uid;
		}

		// Ignore DB-managed fields (id, created_at, updated_at).
		return $out;
	}

	/**
	 * Insert with required fields enforced and uniqueness check for set_post_id.
	 *
	 * @throws WPFlashNotesError
	 */
	public function insert( array $data ): int {
		if ( empty( $data['title'] ) ) {
			throw new WPFlashNotesError(
				'validation',
				'Missing required field: title.',
				400
			);
		}
		if ( empty( $data['set_post_id'] ) ) {
			throw new WPFlashNotesError(
				'validation',
				'Missing required field: set_post_id.',
				400
			);
		}
		if ( empty( $data['user_id'] ) ) {
			throw new WPFlashNotesError(
				'validation',
				'Missing required field: user_id.',
				400
			);
		}

		// Enforce the UNIQUE (set_post_id) invariant.
		if ( $this->get_by_set_post_id( (int) $data['set_post_id'] ) ) {
			throw new WPFlashNotesError(
				'validation',
				'A set already exists for this set_post_id.',
				400
			);
		}

		return parent::insert( $data );
	}

	/**
	 * Protect immutable fields on updates (user_id, set_post_id, timestamps, id).
	 */
	public function update( int $id, array $data ): bool {
		unset(
			$data['user_id'],
			$data['set_post_id'],
			$data['created_at'],
			$data['updated_at'],
			$data['id']
		);
		return parent::update( $id, $data );
	}

	public function get_by_set_post_id( int $set_post_id ): ?array {
		return $this->get_by_column( 'set_post_id', $set_post_id, 1 );
	}

	public function get_by_post_id( int $post_id ): ?array {
		return $this->get_by_column( 'post_id', $post_id, 1 );
	}

	public function list_by_user( int $user_id, int $limit = 20, int $offset = 0 ): array {
		$user_id = $this->validate_id( $user_id );
		$limit   = max( 1, (int) $limit );
		$offset  = max( 0, (int) $offset );

		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->get_table_name()}
			 WHERE user_id = %d
			 ORDER BY id DESC
			 LIMIT %d OFFSET %d",
			$user_id,
			$limit,
			$offset
		);

		$results = $this->wpdb->get_results( $sql, ARRAY_A );
		if ( $results === null ) {
			throw new WPFlashNotesError(
				'db',
				'Failed to fetch user sets: ' . ( $this->wpdb->last_error ?: 'unknown DB error' ),
				500
			);
		}

		return $results ?: array();
	}

	/**
	 * Upsert by set_post_id:
	 *  - If it exists, update safe fields (title, post_id) and return id.
	 *  - If not, require (title, user_id) and insert.
	 *
	 * @throws WPFlashNotesError
	 */
	public function upsert_by_set_post_id( array $data ): int {
		if ( empty( $data['set_post_id'] ) ) {
			throw new WPFlashNotesError(
				'validation',
				'set_post_id is required for upsert.',
				400
			);
		}

		$set_post_id = absint( $data['set_post_id'] );
		$existing    = $this->get_by_set_post_id( $set_post_id );

		if ( $existing ) {
			$payload = array();

			if ( ! empty( $data['title'] ) ) {
				$payload['title'] = $data['title'];
			}
			if ( array_key_exists( 'post_id', $data ) ) {
				$payload['post_id'] = $data['post_id'];
			}

			if ( $payload ) {
				$this->update( (int) $existing['id'], $payload );
			}

			return (int) $existing['id'];
		}

		if ( empty( $data['title'] ) || empty( $data['user_id'] ) ) {
			throw new WPFlashNotesError(
				'validation',
				'Insert requires title and user_id.',
				400
			);
		}

		return $this->insert( array(
			'title'       => $data['title'],
			'post_id'     => $data['post_id'] ?? null,
			'set_post_id' => $set_post_id,
			'user_id'     => (int) $data['user_id'],
		) );
	}
}
