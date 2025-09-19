<?php
namespace WPFlashNotes\Managers;

use WP_Post;
use WPFlashNotes\Repos\NotesRepository;
use WPFlashNotes\Repos\CardsRepository;
use WPFlashNotes\Repos\SetsRepository;
use WPFlashNotes\Repos\NoteSetRelationsRepository;
use WPFlashNotes\Repos\CardSetRelationsRepository;
use WPFlashNotes\Repos\ObjectUsageRepository;
use WPFlashNotes\Helpers\BlockParser;

class SyncManager {

    protected NotesRepository $notes;
    protected CardsRepository $cards;
    protected SetsRepository $sets;
    protected NoteSetRelationsRepository $note_relations;
    protected CardSetRelationsRepository $card_relations;
    protected ObjectUsageRepository $usage;

    public function __construct(
        NotesRepository $notes,
        CardsRepository $cards,
        SetsRepository $sets,
        NoteSetRelationsRepository $note_relations,
        CardSetRelationsRepository $card_relations,
        ObjectUsageRepository $usage
    ) {
        $this->notes          = $notes;
        $this->cards          = $cards;
        $this->sets           = $sets;
        $this->note_relations = $note_relations;
        $this->card_relations = $card_relations;
        $this->usage          = $usage;
    }

    /* --------------------
     * Studyset lifecycle
     * -------------------- */

    protected function get_studyset_for_origin(int $origin_post_id): ?array {
        return $this->sets->get_by_post_id($origin_post_id)[0] ?? null;
    }

    protected function update_studyset(int $set_post_id, string $title, string $content): void {
        wp_update_post([
            'ID'           => $set_post_id,
            'post_title'   => $title,
            'post_content' => $content,
        ]);
    }

    protected function create_studyset(int $origin_post_id, string $title, string $content, int $author): array {
        $set_post_id = wp_insert_post([
            'post_title'   => $title,
            'post_type'    => 'studyset',
            'post_status'  => get_post_status($origin_post_id),
            'post_content' => $content,
            'post_author'  => $author,
        ], true, false);

        if (is_wp_error($set_post_id)) {
            throw new \RuntimeException('Failed to create studyset: '.$set_post_id->get_error_message());
        }

        $this->sets->upsert_by_set_post_id([
            'title'       => $title,
            'post_id'     => $origin_post_id,
            'set_post_id' => $set_post_id,
            'user_id'     => $author,
        ]);

        return $this->sets->get_by_set_post_id($set_post_id);
    }

    /**
     * Ensure studyset exists for a regular origin post (page/post/CPT).
     */
    public function ensure_set_for_post(int $origin_post_id, string $content): array {
        $title  = get_the_title($origin_post_id) ?: __('Untitled', 'wp-flashnotes');
        $author = (int) (get_post_field('post_author', $origin_post_id) ?: get_current_user_id());

        $all_blocks       = BlockParser::parse_raw($content);
        $flashnote_blocks = BlockParser::filter_flashnote_blocks($all_blocks);
        $flashnote_content = serialize_blocks($flashnote_blocks);

        $existing = $this->get_studyset_for_origin($origin_post_id);
        if ($existing) {
            $this->update_studyset((int)$existing['set_post_id'], $title, $flashnote_content);
            return [
                'set_id'        => (int) $existing['id'],
                'set_post_id'   => (int) $existing['set_post_id'],
                'origin_post_id'=> $origin_post_id,
            ];
        }

        if (!empty($flashnote_blocks)) {
            $row = $this->create_studyset($origin_post_id, $title, $flashnote_content, $author);
            return [
                'set_id'        => (int) $row['id'],
                'set_post_id'   => (int) $row['set_post_id'],
                'origin_post_id'=> $origin_post_id,
            ];
        }

        return [];
    }

    /**
     * Ensure studyset row exists when saving a studyset directly.
     */
    public function ensure_set_for_studyset(int $set_post_id, string $content): array {
        $title  = get_the_title($set_post_id) ?: __('Untitled', 'wp-flashnotes');
        $author = (int) (get_post_field('post_author', $set_post_id) ?: get_current_user_id());

        // Always upsert so wpfn_sets row exists
        $this->sets->upsert_by_set_post_id([
            'title'       => $title,
            'post_id'     => $set_post_id,
            'set_post_id' => $set_post_id,
            'user_id'     => $author,
        ]);

        $row = $this->sets->get_by_set_post_id($set_post_id);

        return [
            'set_id'        => (int) $row['id'],
            'set_post_id'   => (int) $row['set_post_id'],
            'origin_post_id'=> $set_post_id,
        ];
    }

    /* --------------------
     * Sync orchestration
     * -------------------- */

    public function sync_pipeline(array $ids, string $content): void {
        if ($ids['origin_post_id'] === $ids['set_post_id']) {
            $this->sync_for_studyset($ids, $content);
        } else {
            $this->sync_for_origin_post($ids, $content);
        }
    }

    protected function sync_for_origin_post(array $ids, string $content): void {
        $blocks = BlockParser::from_post_content($content);
        $this->remove_invalid_relationships($ids['origin_post_id'], $blocks);
        $this->process_blocks($ids, $blocks);
    }

    protected function sync_for_studyset(array $ids, string $content): void {
        $blocks = BlockParser::from_post_content($content);
        $this->process_blocks($ids, $blocks);
    }

    /* --------------------
     * Block processing
     * -------------------- */

    protected function get_block_handlers(): array {
        return [
            'wpfn/note'     => [
                'repository' => $this->notes,
                'relation'   => $this->note_relations,
                'usage_type' => 'note',
            ],
            'wpfn/card'     => [
                'repository' => $this->cards,
                'relation'   => $this->card_relations,
                'usage_type' => 'card',
            ],
            'wpfn/inserter' => [
                'repository' => null,
                'relation'   => null,
                'usage_type' => 'inserter',
            ],
        ];
    }

    protected function process_blocks(array $ids, array $blocks): void {
        if (empty($blocks)) {
            return;
        }

        $handlers = $this->get_block_handlers();
        $set_id   = $ids['set_id'];
        $origin_post_id = $ids['origin_post_id'];

        foreach ($blocks as $block) {
            $this->process_single_block($block, $handlers, $set_id, $origin_post_id);
        }
    }

    protected function process_single_block(array $block, array $handlers, int $set_id, int $origin_post_id): void {
        $block_id   = $block['block_id'] ?? null;
        $block_name = $block['blockName'] ?? null;

        if (!$block_id || !isset($handlers[$block_name])) {
            return;
        }

        $handler = $handlers[$block_name];
        $row_id  = null;

        if (!empty($handler['repository'])) {
            $row_id = $handler['repository']->upsert_from_block($block);
        }

        if ($row_id && !empty($handler['relation'])) {
            $handler['relation']->attach($row_id, $set_id);
        }

        if (!empty($handler['usage_type'])) {
            if ($block_name === 'wpfn/inserter') {
                $row_id = $block['attrs']['id'] ?? null;
            }

            if ($row_id) {
                $this->usage->attach(
                    $handler['usage_type'],
                    $row_id,
                    $origin_post_id,
                    $block_id
                );

                $this->maybe_reactivate_card((int)$row_id, $block_name);
            }
        }
    }

    /* --------------------
     * Cleanup / Orphans
     * -------------------- */

    public function remove_invalid_relationships(int $post_id, array $parsed_objects): void {
        $blocks_in_post = [];
        foreach ($parsed_objects as $block) {
            if (!empty($block['attrs']['block_id'])) {
                $blocks_in_post[] = $block['attrs']['block_id'];
            }
        }

        $items_in_db = $this->usage->get_relationships_by_column('post_id', $post_id);

        foreach ($items_in_db as $item) {
            if (!in_array($item['block_id'], $blocks_in_post, true)) {
                $this->usage->detach(
                    $item['object_type'],
                    $item['object_id'],
                    $item['post_id'],
                    $item['block_id']
                );
                $this->maybe_tag_as_orphan($item);
            }
        }
    }

    public function sync_on_deleted(WP_Post $post): void {
        $blocks = BlockParser::from_post_content($post->post_content);
        foreach ($blocks as $block) {
            $this->maybe_tag_as_orphan($block);
        }
    }

    protected function maybe_tag_as_orphan(array $block): void {
        if ($block['object_type'] === 'inserter') {
            return;
        }

        $related_in_db = $this->usage->get_relationships_by_column('block_id', $block['block_id']);
        $repo = $block['object_type'] === 'card' ? $this->cards : $this->notes;

        if (count($related_in_db) === 0) {
            $repo->update($block['object_id'], ['status' => 'orphan']);
        }
    }

    protected function maybe_reactivate_card(int $child_id, string $parent_block_name): void {
        if ($parent_block_name !== 'wpfn/inserter') {
            return;
        }

        $current = $this->cards->read($child_id);
        if ($current && $current['status'] === 'orphan') {
            $this->cards->update($child_id, ['status' => 'active']);
        }
    }
}
