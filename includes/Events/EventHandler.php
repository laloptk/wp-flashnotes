<?php
namespace WPFlashNotes\Events;

use WP_Post;
use WPFlashNotes\Managers\SyncManager;

class EventHandler {

    protected SyncManager $sync;

    public function __construct(SyncManager $sync) {
        $this->sync = $sync;
    }

    /**
     * Handle post save events for studysets and origin posts.
     */
    public function on_post_save(int $post_id, WP_Post $post, bool $update): void {
        if ($this->is_autosave_or_revision($post_id) || $post->post_status === 'auto-draft') {
            return;
        }

        if ($post->post_type === 'studyset') {
            // Saving a studyset directly
            $ids = $this->sync->ensure_set_for_studyset($post_id, $post->post_content);
            if (!empty($ids)) {
                $this->sync->sync_pipeline($ids, $post->post_content);
            }
            return;
        }

        // Saving a regular post/page/CPT
        $ids = $this->sync->ensure_set_for_post($post_id, $post->post_content);

        if (!empty($ids)) {
            $this->update_studyset_status($ids['set_post_id'], $post);
            $this->sync->sync_pipeline($ids, $post->post_content);
        }
    }

    /**
     * Handle post delete events → mark orphaned and detach relationships.
     */
    public function on_post_deleted(int $post_id, WP_Post $post): void {
        $this->sync->sync_on_deleted($post);
    }

    /* --------------------
     * Helpers
     * -------------------- */

    /**
     * Ensure the studyset has a valid post_status (draft or publish).
     */
    protected function update_studyset_status(int $set_post_id, WP_Post $origin): void {
        $origin_status = get_post_status($origin->ID);
        $new_status = ($origin_status === 'publish') ? 'publish' : 'draft';

        wp_update_post([
            'ID'          => $set_post_id,
            'post_status' => $new_status,
        ]);
    }

    protected function is_autosave_or_revision(int $post_id): bool {
        return wp_is_post_autosave($post_id) || wp_is_post_revision($post_id);
    }
}
