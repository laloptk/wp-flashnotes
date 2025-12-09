<?php

namespace WPFlashNotes\DataBase;

use WPFlashNotes\Repos\CardsRepository;
use WPFlashNotes\Repos\NotesRepository;
use WPFlashNotes\Repos\SetsRepository;
use WPFlashNotes\Repos\CardSetRelationsRepository;
use WPFlashNotes\Repos\NoteSetRelationsRepository;
use WPFlashNotes\Repos\ObjectUsageRepository;


class DataPropagation {
	private CardsRepository $cards;
	private NotesRepository $notes;
	private SetsRepository $sets;
	private CardSetRelationsRepository $card_set;
	private NoteSetRelationsRepository $note_set;
	private ObjectUsageRepository $usage;

	public function __construct(
		CardsRepository $cards,
		NotesRepository $notes,
		SetsRepository $sets,
		CardSetRelationsRepository $card_set_rel,
		NoteSetRelationsRepository $note_set_rel,
		ObjectUsageRepository $usage
	) {
		$this->cards    = $cards;
		$this->notes    = $notes;
		$this->sets     = $sets;
		$this->card_set = $card_set_rel;
		$this->note_set = $note_set_rel;
		$this->usage    = $usage;
	}

	public function propagate( int $post_id, array $blocks ): void {
		$post    = get_post( $post_id );
		$set_row = null;
		$set_id  = null;

		if ( $post->post_type === 'studyset' && $post->post_status !== 'auto-draft' ) {
			$set_row = $this->sets->get_by_set_post_id( $post_id );

			if ( empty( $set_row ) ) {
				$set_id = $this->sets->upsert_by_set_post_id(
					array(
						'title'       => ! empty( $post->post_title ) ? $post->post_title : 'Untitled',
						'post_id'     => $post_id,
						'set_post_id' => $post_id,
						'user_id'     => $post->post_author,
					)
				);
			} else {
				$set_id = $set_row['id'];
				if ( isset( $set_row['title'] ) && $set_row['title'] !== $post->post_title ) {
					$this->sets->update(
						$set_id,
						array( 'title' => $post->post_title )
					);
				}
			}
		}

		foreach ( $blocks as $block ) {
			if ( $block['object_type'] === 'card' ) {
				//error_log("From the propagate function:" . json_encode($block));
				$card_id = $this->cards->upsert_from_block( $block );

				if ( $post->post_type === 'studyset' && absint( $set_id ) > 0 ) {
					$this->card_set->attach( $card_id, (int) $set_id );
				}

				$this->usage->attach( 'card', $card_id, $post_id, $block['block_id'] );

				continue;
			}

			if ( $block['object_type'] === 'note' ) {
				$note_id = $this->notes->upsert_from_block( $block );

				if ( $post->post_type === 'studyset' && absint( $set_id ) > 0 ) {
					$this->note_set->attach( $note_id, (int) $set_id );
				}

				$this->usage->attach( 'note', $note_id, $post_id, $block['block_id'] );
				continue;
			}

			if ( $block['object_type'] === 'inserter' ) {
				if ( ! empty( $block['attrs']['id'] ) && ! empty( $block['attrs']['card_block_id'] ) ) {
					$this->usage->attach( 'inserter', (int) $block['attrs']['id'], $post_id, $block['attrs']['card_block_id'] );
				}

				$this->tag_as_active( $block );

				continue;
			}
		}
	}

	public function remove_invalid_relationships( int $post_id, array $parsed_objects ): void {
		// Build an associative map of [block_id][object_type] => true
		$blocks_in_post = array();

		foreach ( $parsed_objects as $block ) {
			$block_id = $block['object_type'] === 'inserter'
				? $block['attrs']['card_block_id']
				: ( $block['block_id'] ?? null );

			$object_type = $block['object_type'] ?? null;

			if ( $block_id && $object_type ) {
				$blocks_in_post[ $block_id ][ $object_type ] = true;
			}
		}

		// Fetch existing relationships for this post
		$items_in_db = $this->usage->get_relationships_by_column( 'post_id', $post_id );

		foreach ( $items_in_db as $item ) {
			$block_id    = $item['block_id'];
			$object_type = $item['object_type'];

			// If the pair doesn’t exist in the new post content, remove it
			if ( empty( $blocks_in_post[ $block_id ][ $object_type ] ) ) {
				$this->usage->detach(
					$object_type,
					$item['object_id'],
					$item['post_id'],
					$block_id
				);

				$this->tag_as_orphan( $item );
			}
		}
	}

	public function get_studyset_for_origin_post( int $origin_post_id ): ?int {
		$row = $this->sets->get_by_post_id( $origin_post_id );
		return $row ? (int) $row['set_post_id'] : null;
	}

	public function register_post_set_relation( array $data ) {
		return $this->sets->upsert_by_set_post_id( $data );
	}

	public function update_post_set_relationship( int $post_id, string $post_type ): void {
		if ( $post_type !== 'studyset' ) {
			$relationship = $this->sets->get_by_post_id( $post_id );

			if ( $relationship ) {
				$this->sets->update(
					$relationship['id'],
					array( 'post_id' => $relationship['set_post_id'] )
				);
			}
		}
	}

	public function orphan_by_post_id( int $post_id ): void {
		$relationships = $this->usage->get_relationships_by_column( 'post_id', $post_id );

		if ( empty( $relationships ) ) {
			return;
		}

		foreach ( $relationships as $item ) {
			$this->tag_as_orphan( $item );
		}
	}

	public function tag_as_orphan( $block ) {
		if ( $block['object_type'] === 'inserter' ) {
			return;
		}

		$related_in_db = $this->usage->get_relationships_by_column( 'block_id', $block['block_id'] );
		$repo          = $block['object_type'] === 'card' ? $this->cards : $this->notes;
		$flashnote     = $repo->get_by_column( 'block_id', $block['block_id'], 1 );

		if ( count( $related_in_db ) === 0 && ! empty( $flashnote ) ) {
			$repo->update( $flashnote['id'], array( 'status' => 'orphan' ) );
		}
	}

	public function tag_as_active( array $block ): void {
		if ( $block['object_type'] !== 'inserter' ) {
			return;
		}

		// Object type must come from inserter attributes
		$object_id = $block['attrs']['id'] ?? null;
		$is_card   = isset( $block['attrs']['card_block_id'] );

		if ( empty( $object_id ) ) {
			return;
		}

		$repo    = $is_card ? $this->cards : $this->notes;
		$current = $repo->read( $object_id );

		if ( $current && $current['status'] === 'orphan' ) {
			$repo->update( $object_id, array( 'status' => 'active' ) );
		}
	}
}
