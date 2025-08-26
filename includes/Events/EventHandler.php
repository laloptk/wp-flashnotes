<?php

namespace WPFlashNotes\Events;

defined( 'ABSPATH' ) || exit;

use WP_Post;
use WP_REST_Request;
use WPFlashNotes\Managers\StudySetManager;

class EventHandler {

	protected StudySetManager $manager;

	public function __construct( StudySetManager $manager ) {
		$this->manager = $manager;
	}

	/**
	 * Handle save_post for normal posts/pages.
	 */
	public function on_post_save( int $post_id, WP_Post $post, bool $update ): void {
		if ( $this->is_autosave_or_revision( $post_id ) ) {
			return;
		}

		$this->manager->handle_post_save( $post_id, $post->post_content );
	}

	/**
	 * Handle save_post for studyset CPT.
	 */
	public function on_studyset_save( int $post_id, WP_Post $post, bool $update ): void {
		if ( $this->is_autosave_or_revision( $post_id ) ) {
			return;
		}

		$this->manager->sync_studyset( $post_id, $post->post_content );
	}

	/**
	 * Handle rest_after_insert_studyset.
	 */
	public function on_studyset_create( WP_Post $post, WP_REST_Request $request, bool $creating ): void {
		if ( ! $creating ) {
			return;
		}

		$this->manager->handle_studyset_create( $post );
	}

	/**
	 * Handle before_update_post for studyset CPT.
	 */
	public function on_studyset_before_update( int $post_id, array $post ): void {
		if ( $this->is_autosave_or_revision( $post_id ) ) {
			return;
		}

		$this->manager->handle_studyset_before_update( $post_id, $post );
	}

	/**
	 * Shared guard: skip autosaves/revisions.
	 */
	protected function is_autosave_or_revision( int $post_id ): bool {
		return wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id );
	}
}
