<?php

namespace WPFlashNotes\Managers;

defined( 'ABSPATH' ) || exit;

use WP_Post;
use WPFlashNotes\Repos\NotesRepository;
use WPFlashNotes\Repos\CardsRepository;
use WPFlashNotes\Repos\SetsRepository;
use WPFlashNotes\Repos\NoteSetRelationsRepository;
use WPFlashNotes\Repos\CardSetRelationsRepository;
use WPFlashNotes\Repos\ObjectUsageRepository;
use WPFlashNotes\Helpers\BlockParser;

class StudySetManager {

	protected NotesRepository $notes;
	protected CardsRepository $cards;
	protected SetsRepository $sets;
	protected NoteSetRelationsRepository $noteRelations;
	protected CardSetRelationsRepository $cardRelations;
	protected ObjectUsageRepository $usage;

	public function __construct(
		NotesRepository $notes,
		CardsRepository $cards,
		SetsRepository $sets,
		NoteSetRelationsRepository $noteRelations,
		CardSetRelationsRepository $cardRelations,
		ObjectUsageRepository $usage
	) {
		$this->notes         = $notes;
		$this->cards         = $cards;
		$this->sets          = $sets;
		$this->noteRelations = $noteRelations;
		$this->cardRelations = $cardRelations;
		$this->usage         = $usage;
	}

	/**
	 * Handle normal post/page save.
	 * Ensure studyset exists if blocks are present.
	 */
	public function handle_post_save( int $post_id, string $post_content ): void {
		$blocks = BlockParser::from_post_content( $post_content );
		if ( empty( $blocks ) ) {
			return;
		}

		$this->sets->ensure_set_for_post( $post_id );
		// Usage tracking deferred until studyset save
	}

	/**
	 * Handle studyset creation (insert row in wpfn_sets).
	 */
	public function handle_studyset_create( WP_Post $post ): void {
		$this->sets->upsert_by_set_post_id(
			array(
				'title'       => $post->post_title,
				'set_post_id' => $post->ID,
				'user_id'     => (int) ( $post->post_author ?: get_current_user_id() ),
			)
		);
	}

	/**
	 * Pre-update hook for studyset: validations, locks, diffs.
	 */
	public function handle_studyset_before_update(int $post_id, \WP_Post $post): void
	{
		if ($post->post_type !== 'studyset') {
			return;
		}

		// Lock guard (optional)
		if (method_exists($this->sets, 'is_locked') && $this->sets->is_locked($post_id)) {
			throw new \Exception("StudySet {$post_id} is locked and cannot be updated.");
		}

		// Parse blocks
		$blocks = BlockParser::from_post_content($post->post_content);

		foreach ($blocks as $block) {
			$blockName = $block['blockName'];
			$blockId   = $block['block_id'];

			if (!$blockId) {
				continue; // skip invalid block
			}

			if ($blockName === 'wpfn/note') {
				// Look up existing row
				$existing = $this->notes->get_by_block_id($blockId);

				if ($existing) {
					// Always trust the block for now (simplest strategy)
					$this->notes->update((int) $existing['id'], [
						'title'   => $block['attrs']['title'] ?? '',
						'content' => $block['attrs']['content'] ?? '',
					]);
				} else {
					// Insert new note
					$this->notes->insert([
						'block_id' => $blockId,
						'title'    => $block['attrs']['title'] ?? '',
						'content'  => $block['attrs']['content'] ?? '',
						'user_id'  => (int) ($post->post_author ?: get_current_user_id()),
					]);
				}
			}

			if ($blockName === 'wpfn/card') {
				$existing = $this->cards->get_by_block_id($blockId);

				if ($existing) {
					$this->cards->update((int) $existing['id'], [
						'question'           => $block['attrs']['question'] ?? '',
						'answers_json'       => $block['attrs']['answers_json'] ?? '[]',
						'right_answers_json' => $block['attrs']['right_answers_json'] ?? '[]',
						'explanation'        => $block['attrs']['explanation'] ?? null,
					]);
				} else {
					$this->cards->insert([
						'block_id'           => $blockId,
						'question'           => $block['attrs']['question'] ?? '',
						'answers_json'       => $block['attrs']['answers_json'] ?? '[]',
						'right_answers_json' => $block['attrs']['right_answers_json'] ?? '[]',
						'explanation'        => $block['attrs']['explanation'] ?? null,
						'user_id'            => (int) ($post->post_author ?: get_current_user_id()),
					]);
				}
			}
		}
	}


	/**
	 * Full sync: blocks ↔ DB rows ↔ relations.
	 */
	public function sync_studyset( int $set_post_id, string $post_content ): void {
		$blocks = BlockParser::from_post_content( $post_content );

		foreach ( $blocks as $block ) {
			$blockId   = $block['block_id'];
			$blockName = $block['blockName'];

			if ( $blockName === 'wpfn/note' ) {
				$note_id = $this->notes->upsert_from_block( $block );
				$this->noteRelations->attach( $note_id, $set_post_id );
				$this->usage->attach( 'note', $note_id, $set_post_id, $blockId );
			}

			if ( $blockName === 'wpfn/card' ) {
				$card_id = $this->cards->upsert_from_block( $block );
				$this->cardRelations->attach( $card_id, $set_post_id );
				$this->usage->attach( 'card', $card_id, $set_post_id, $blockId );
			}
		}
	}
}
