<?php

namespace WPFlashNotes\Repos;

defined( 'ABSPATH' ) || exit;

use Exception;
use WPFlashNotes\BaseClasses\BaseRepository;

/**
 * SetsRepository
 *
 * CRUD + helpers for the {prefix}wpfn_sets table.
 * Schema recap:
 *  - id BIGINT UNSIGNED PK AI
 *  - title VARCHAR(255) NOT NULL
 *  - post_id BIGINT UNSIGNED NULL
 *  - set_post_id BIGINT UNSIGNED NOT NULL UNIQUE (FK to wp_posts.ID for the Study Set CPT)
 *  - user_id BIGINT UNSIGNED NOT NULL (FK to wp_users.ID)
 *  - created_at DATETIME DEFAULT CURRENT_TIMESTAMP
 *  - updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
 */
class SetsRepository extends BaseRepository {

	/**
	 * Cache full table name.
	 */
	protected string $table;

	public function __construct() {
		parent::__construct();
		$this->table = $this->wpdb->prefix . 'wpfn_sets';
	}

	/**
	 * BaseRepository requirement.
	 */
	protected function get_table_name(): string {
		return $this->table;
	}

	/**
	 * Map of field formats for wpdb insert/update.
	 *
	 * NOTE: We omit formats for fields we purposely don't accept (e.g., id).
	 * For nullable columns like post_id, we simply don't include the key
	 * when the value is null/zero so wpdb won't coerce it to 0.
	 */
	protected function fieldFormats(): array {
		return array(
			'title'       => '%s',
			'post_id'     => '%d',
			'set_post_id' => '%d',
			'user_id'     => '%d',
			// created_at / updated_at are DB-managed; never set from app.
		);
	}

	/**
	 * Sanitize & validate payload.
	 *
	 * Rules:
	 *  - title: required (non-empty) when present; sanitized text
	 *  - post_id: optional; if falsy -> omitted (keeps NULL in DB)
	 *  - set_post_id: positive int when present
	 *  - user_id: positive int when present
	 *  - created_at / updated_at: ignored (DB-managed)
	 *  - id: ignored (auto-increment)
	 *
	 * Throw Exception on invalid input.
	 */
	protected function sanitize_data( array $data ): array {
		$out = array();

		if ( array_key_exists( 'title', $data ) ) {
			$title = trim( wp_strip_all_tags( (string) $data['title'] ) );
			if ( $title === '' ) {
				throw new Exception( 'Title cannot be empty.' );
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
				throw new Exception( 'set_post_id must be a positive integer.' );
			}
			$out['set_post_id'] = $set_post_id;
		}

		if ( array_key_exists( 'user_id', $data ) ) {
			$uid = absint( $data['user_id'] );
			if ( $uid <= 0 ) {
				throw new Exception( 'user_id must be a positive integer.' );
			}
			$out['user_id'] = $uid;
		}

		// Ignore DB-maintained timestamps & id if passed.
		return $out;
	}

	/**
	 * Sets table has NO soft-delete column -> fallback to hard delete.
	 */
	protected function soft_delete_column(): ?string {
		return null;
	}

	/**
	 * Insert with required fields enforced and uniqueness check for set_post_id.
	 */
	public function insert( array $data ): int {
		if ( empty( $data['title'] ) ) {
			throw new Exception( 'Missing required field: title.' );
		}
		if ( empty( $data['set_post_id'] ) ) {
			throw new Exception( 'Missing required field: set_post_id.' );
		}
		if ( empty( $data['user_id'] ) ) {
			throw new Exception( 'Missing required field: user_id.' );
		}

		// Enforce the UNIQUE (set_post_id) invariant at app-level for a better error.
		if ( $this->get_by_set_post_id( (int) $data['set_post_id'] ) ) {
			throw new Exception( 'A set already exists for this set_post_id.' );
		}

		return parent::insert( $data );
	}

	/**
	 * Protect immutable fields on updates (user_id, set_post_id, timestamps, id).
	 * If you *do* want to allow re-binding in the future, remove unset() as needed.
	 */
	public function update( int $id, array $data ): bool {
		unset( $data['user_id'], $data['set_post_id'], $data['created_at'], $data['updated_at'], $data['id'] );
		return parent::update( $id, $data );
	}

	/**
	 * Lookup by set_post_id (unique).
	 */
	public function get_by_set_post_id( int $set_post_id ): ?array {
		return $this->get_by_column( 'set_post_id', $set_post_id, 1 );
	}

	/**
	 * Fetch all sets that originate from a given content post (non-unique).
	 */
	public function get_by_post_id( int $post_id ): ?array {
		return $this->get_by_column( 'post_id', $post_id );
	}

	/**
	 * List sets belonging to a user with paging.
	 */
	public function list_by_user( int $user_id, int $limit = 20, int $offset = 0 ): array {
		$user_id = $this->validate_id( $user_id );
		$limit   = max( 1, (int) $limit );
		$offset  = max( 0, (int) $offset );

		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->get_table_name()} WHERE user_id = %d ORDER BY id DESC LIMIT %d OFFSET %d",
			$user_id,
			$limit,
			$offset
		);

		return $this->wpdb->get_results( $sql, ARRAY_A ) ?: array();
	}

	/**
	 * Upsert by set_post_id:
	 *  - If it exists, update safe fields (title, post_id) and return id.
	 *  - If not, require (title, user_id) and insert.
	 */
	public function upsert_by_set_post_id( array $data ): int {
		if ( empty( $data['set_post_id'] ) ) {
			throw new Exception( 'set_post_id is required for upsert.' );
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
			throw new Exception( 'Insert requires title and user_id.' );
		}

		return $this->insert(
			array(
				'title'       => $data['title'],
				'post_id'     => $data['post_id'] ?? null,
				'set_post_id' => $set_post_id,
				'user_id'     => (int) $data['user_id'],
			)
		);
	}
}
