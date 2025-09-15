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
		$this->notes         = $notes;
		$this->cards         = $cards;
		$this->sets          = $sets;
		$this->note_relations = $note_relations;
		$this->card_relations = $card_relations;
		$this->usage         = $usage;
	}

	/**
	 * Ensure or update a studyset for a given origin post (non-studyset).
	 */
	public function ensure_set_for_post( int $origin_post_id, string $content ): int {
		$title  = get_the_title( $origin_post_id ) ?: 'Untitled';
		$author = (int) ( get_post_field( 'post_author', $origin_post_id ) ?: get_current_user_id() );

		$existing = $this->sets->get_by_post_id( $origin_post_id );
		if ( ! empty( $existing ) ) {
			$set_post_id = (int) $existing[0]['set_post_id'];

			wp_update_post(
				[
					'ID'           => $set_post_id,
					'post_title'   => $title,
					'post_content' => $content,
				]
			);

			return $set_post_id;
		}

		$set_post_id = wp_insert_post(
			[
				'post_title'   => $title,
				'post_type'    => 'studyset',
				'post_status'  => get_post_status( $origin_post_id ),
				'post_content' => $content,
				'post_author'  => $author,
			]
		);

		if ( is_wp_error( $set_post_id ) ) {
			throw new \RuntimeException( 'Failed to create studyset: ' . $set_post_id->get_error_message() );
		}

		$this->sets->upsert_by_set_post_id(
			[
				'title'       => $title,
				'post_id'     => $origin_post_id,
				'set_post_id' => $set_post_id,
				'user_id'     => $author,
			]
		);

		return (int) $set_post_id;
	}

	/**
	 * Sync cards and notes for a given studyset, with change detection.
	 */
	public function sync_studyset( int $set_post_id, string $content ): void {
		$blocks = BlockParser::from_post_content( $content );

		if ( empty( $blocks ) ) {
			return;
		}

		// Resolve set row once
		$set_row = $this->sets->get_by_set_post_id( $set_post_id );
		
		if ( ! $set_row ) {
			// Graceful fallback: don't throw in editor context
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "WP FlashNotes: No wpfn_sets row found for set_post_id {$set_post_id}" );
			}
			return; // simply skip syncing
		}

		$set_id = (int) $set_row['id'];

		// Map block names to their handlers
		$handlers = [
			'wpfn/note' => [
				'repository' => $this->notes,
				'relation'   => $this->note_relations,
				'usage_type' => 'note',
			],
			'wpfn/card' => [
				'repository' => $this->cards,
				'relation'   => $this->card_relations,
				'usage_type' => 'card',
			],
			'wpfn/inserter' => [
				'repository' => null, // no DB table for inserters
				'relation'   => null, // no pivot relation
				'usage_type' => 'inserter',
			],
		];

		foreach ( $blocks as $block ) {
			$block_id   = $block['block_id'];
			$block_name = $block['blockName'];

			if ( ! $block_id || ! isset( $handlers[ $block_name ] ) ) {
				continue;
			}

			$handler = $handlers[ $block_name ];

			if( ! empty( $handler['repository'] ) ) {
				$row_id = $handler['repository']->upsert_from_block( $block );
			}

			if( ! empty( $handler['relation'] ) ) {
				$handler['relation']->attach( $row_id, $set_id );
			}

			if( ! empty( $handler['usage_type'] ) ) {
				if($block_name === 'wpfn/inserter') {
					$row_id = $block['attrs']['id'];
				}

				$this->usage->attach( $handler['usage_type'], $row_id, $set_post_id, $block_id );
			}
		}
	}

	public function sync_post_objects($post_id, $parsed_objects) {
		// Get block id's from $parsed_objects (Only parent block ids)
		// Get block id's by $post_id from the usage table
		// Get id's that are not in the post, but they are in the DB
		// Delete the object usage for every result (from the comparision)
		// Check if there is other relationships for that block_id in object_usage
			// If the result is > 1, leave it as it is
			// Else tag the object as orphaned
	}
}
