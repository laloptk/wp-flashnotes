<?php
namespace WPFlashNotes\Events;

use WP_Post;
use WPFlashNotes\Managers\SyncManager;

class EventHandler {

	protected SyncManager $sync;

	public function __construct( SyncManager $sync ) {
		$this->sync = $sync;
	}

	public function on_post_save( int $post_id, WP_Post $post, bool $update ): void {
		if ( $this->is_autosave_or_revision( $post_id ) ) {
			return;
		}

		if ( $post->post_type === 'studyset' ) {
			$this->sync->sync_studyset( $post_id, $post->post_content );
			return;
		}

		// Create or update the studyset itself
		$set_post_id = $this->sync->ensure_set_for_post( $post_id, $post->post_content );

		// Normalize/update its status after save_post is done
		$this->update_studyset_status( $set_post_id, $post );

		// Sync data into custom DB tables
		$this->sync->sync_studyset( $set_post_id, $post->post_content );
	}

	/**
	 * Ensure the studyset has a valid post_status (draft or publish).
	 */
	protected function update_studyset_status( int $set_post_id, WP_Post $origin ): void {
		$origin_status = get_post_status( $origin->ID );

		$new_status = ( $origin_status === 'publish' ) ? 'publish' : 'draft';

		wp_update_post( [
			'ID'          => $set_post_id,
			'post_status' => $new_status,
		] );
	}

	protected function is_autosave_or_revision( int $post_id ): bool {
		return wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id );
	}
}
