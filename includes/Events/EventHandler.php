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
		if ( $this->is_autosave_or_revision( $post_id ) || $post->post_status === 'auto-draft' ) {
			return;
		}

		// Case 1: Saving a studyset directly
		if ( $post->post_type === 'studyset' ) {
			$ids = array(
				'set_post_id'    => $post_id,
				'origin_post_id' => $post_id,
			);
			$this->sync->sync_studyset( $ids, $post->post_content );
			return;
		}

		// Case 2: Saving a regular post/page/CPT
		$ids = $this->sync->ensure_set_for_post( $post_id, $post->post_content );

		$this->update_studyset_status( $ids['set_post_id'], $post );

		$this->sync->sync_studyset( $ids, $post->post_content );
	}


	/**
	 * Ensure the studyset has a valid post_status (draft or publish).
	 */
	protected function update_studyset_status( int $set_post_id, WP_Post $origin ): void {
		$origin_status = get_post_status( $origin->ID );

		$new_status = ( $origin_status === 'publish' ) ? 'publish' : 'draft';

		wp_update_post(
			array(
				'ID'          => $set_post_id,
				'post_status' => $new_status,
			)
		);
	}

	protected function is_autosave_or_revision( int $post_id ): bool {
		return wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id );
	}
}
