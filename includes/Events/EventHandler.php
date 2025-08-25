<?php 

namespace WPFlashNotes\Events;

use WPFlashNotes\Repos\NotesRepository;
use WPFlashNotes\Repos\CardsRepository;
use WPFlashNotes\Repos\SetsRepository;
use WPFlashNotes\Repos\ObjectUsageRepository;
use WPFlashNotes\Managers\StudySetManager;
use WPFlashNotes\Helpers\BlockParser;

class EventHandler {

    protected NotesRepository $notes;
    protected CardsRepository $cards;
    protected SetsRepository $sets;
    protected ObjectUsageRepository $usage;
    protected StudySetManager $manager;
    public function __construct(
        NotesRepository $notes,
        CardsRepository $cards,
        SetsRepository $sets,
        ObjectUsageRepository $usage,
        StudySetManager $manager,
    ) {
        $this->notes   = $notes;
        $this->cards   = $cards;
        $this->sets    = $sets;
        $this->usage   = $usage;
        $this->manager = $manager;
    }

    /**
     * Handle save_post for normal posts/pages.
     *
     * Responsibilities:
     * - Skip autosaves/revisions.
     * - Check if the post contains any FlashNotes blocks.
     * - Ensure a StudySet (CPT + wpfn_sets row) exists for this post.
     * - Usage tracking (block_id ↔ object_id) is deferred to on_studyset_save.
     */
    public function on_post_save(int $post_id, WP_Post $post, bool $update): void
    {
        // 1. Skip autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // 2. Extract FlashNotes blocks
        $flashnotesBlocks = BlockParser::from_post_content($post->post_content);

        if (empty($flashnotesBlocks)) {
            return; // No FlashNotes blocks → nothing to do
        }

        // 3. Ensure studyset exists for this post
        $this->sets->ensure_set_for_post($post_id);
    }

    public function on_studyset_save($set_id, $post_id) {

    }

    public function on_studyset_create($set_id, $post_id) {

    }

    public function on_studyset_before_update($set_id) {

    }
}