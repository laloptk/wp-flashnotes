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
use WPFlashNotes\Helpers\BlockHelpers;

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

	/**
	 * Ensure or update a studyset for a given origin post (non-studyset).
	 *
	 * @param int    $origin_post_id Origin post ID (page, post, or CPT).
	 * @param string $content        Post content (block JSON).
	 * @return array{set_post_id:int, origin_post_id:int}
	 */
	public function ensure_set_for_post( int $origin_post_id, string $content ): array {
		$title  = get_the_title( $origin_post_id ) ?: __( 'Untitled', 'wp-flashnotes' );
		$author = (int) ( get_post_field( 'post_author', $origin_post_id ) ?: get_current_user_id() );

		// 1. Parse post into blocks
		$all_blocks       = BlockParser::parse_raw( $content );
		$flashnote_blocks = BlockParser::filter_flashnote_blocks( $all_blocks );

		// 2. Only flashnotes go into the studyset post_content
		$flashnote_content = serialize_blocks( $flashnote_blocks );

		// 3. Get existing studyset (if any)
		$existing = $this->sets->get_by_post_id( $origin_post_id );
		if ( ! empty( $existing ) ) {
			$set_post_id = (int) $existing[0]['set_post_id'];

			$post_args = array(
				'post_title'   => $title,
				'post_content' => $flashnote_content,
			);

			$this->update_post( $set_post_id, $post_args );

			return array(
				'set_post_id'    => $set_post_id,
				'origin_post_id' => $origin_post_id,
			);
		}

		// 4. Insert a new studyset only if there are flashnote blocks
		if ( ! empty( $flashnote_blocks ) ) {
			$set_post_id = wp_insert_post(
				array(
					'post_title'   => $title,
					'post_type'    => 'studyset',
					'post_status'  => get_post_status( $origin_post_id ),
					'post_content' => $flashnote_content,
					'post_author'  => $author,
				)
			);

			if ( is_wp_error( $set_post_id ) ) {
				throw new \RuntimeException(
					sprintf(
						/* translators: %s is the WP error message. */
						__( 'Failed to create studyset: %s', 'wp-flashnotes' ),
						$set_post_id->get_error_message()
					)
				);
			}

			$this->sets->upsert_by_set_post_id(
				array(
					'title'       => $title,
					'post_id'     => $origin_post_id,
					'set_post_id' => $set_post_id,
					'user_id'     => $author,
				)
			);

			return array(
				'set_post_id'    => (int) $set_post_id,
				'origin_post_id' => $origin_post_id,
			);
		}

		// 5. No studyset created if no flashnotes
		return array();
	}

	public function sync_pipeline( array $ids, string $content ): void {
		$set_post_id    = $ids['set_post_id'];
		$origin_post_id = $ids['origin_post_id'];

		$all_blocks       = BlockParser::parse_raw( $content );
		$flashnote_blocks = BlockParser::filter_flashnote_blocks( $all_blocks );
		$objects          = BlockParser::normalize_to_objects( $flashnote_blocks );

		$this->remove_invalid_relationships( $origin_post_id, $objects );

		if ( empty( $flashnote_blocks ) ) {
			return;
		}

		// Propagate from studyset → origin post if needed
		$this->sync_post_from_studyset( $ids, $flashnote_blocks );

		// Ensure wpfn_sets row exists (handles direct studyset creation)
		$set_row = $this->sets->get_by_set_post_id( $set_post_id );
		if ( empty($set_row) ) {
			$author = (int) get_post_field( 'post_author', $set_post_id );

			$set_id = $this->sets->upsert_by_set_post_id([
				'title'       => get_the_title( $set_post_id ),
				'post_id'     => $origin_post_id > 0 ? $origin_post_id : $set_post_id,
				'set_post_id' => $set_post_id,
				'user_id'     => $author,
			]);
		}

		$set_id = ! empty( $set_row ) ? (int) $set_row['id'] : $set_id;

		// Map block names to their handlers
		$handlers = array(
			'note'     => array(
				'repository' => $this->notes,
				'relation'   => $this->note_relations,
				'usage_type' => 'note',
			),
			'card'     => array(
				'repository' => $this->cards,
				'relation'   => $this->card_relations,
				'usage_type' => 'card',
			),
			'inserter' => array(
				'repository' => null,
				'relation'   => null,
				'usage_type' => 'inserter',
			),
		);

		foreach ( $objects as $block ) {
			$block_id   = $block['block_id'] ?? null;
			$block_name = $block['object_type'] ?? null;

			if ( ! $block_id || ! isset( $handlers[ $block_name ] ) ) {
				continue;
			}

			$handler = $handlers[ $block_name ];
			$row_id  = null;

			if ( ! empty( $handler['repository'] ) ) {
				$row_id = $handler['repository']->upsert_from_block( $block );
			}

			if ( $row_id && ! empty( $handler['relation'] ) ) {
				$handler['relation']->attach( $row_id, $set_id );
			}

			if ( ! empty( $handler['usage_type'] ) ) {
				if ( $block_name === 'inserter' ) {
					if ( empty( $block['attrs']['id'] ) ) {
						continue;
					}
					$row_id = $block['attrs']['id'];
				}

				$this->usage->attach(
					$handler['usage_type'],
					$row_id,
					$origin_post_id,
					$block_id
				);

				$this->maybe_reactivate_card( (int) $row_id, $block_name );
			}
		}
	}

	public function sync_on_deleted( WP_Post $post ): void {
		$blocks = BlockParser::from_post_content( $post->post_content );
		foreach ( $blocks as $block ) {
			// DB Cascading takes care of removing invalid relationships
			$this->maybe_tag_as_orphan( $block );
		}

		if ( $post->post_type !== 'studyset' ) {
			$set_row = $this->sets->get_by_post_id( $post->ID );

			if ( ! empty( $set_row ) && isset( $set_row[0] ) ) {
				$this->sets->update( $set_row[0]['id'], array( 'post_id' => $set_row[0]['set_post_id'] ) );
			}
		}
	}

	public function sync_post_from_studyset(array $ids, array $parsed_set_blocks) {
		if (get_post_type($ids['set_post_id']) !== 'studyset' || $ids['set_post_id'] === $ids['origin_post_id']) {
			return;
		}

		$origin_post = get_post($ids['origin_post_id']);
		$parsed_origin_blocks = BlockParser::parse_raw($origin_post->post_content);

		$merged_blocks = BlockHelpers::merge_gutenberg_blocks($parsed_origin_blocks, $parsed_set_blocks);
		$updated_post_content = serialize_blocks($merged_blocks);

		$this->update_post($ids['origin_post_id'], [
			'post_content' => $updated_post_content
		]);
	}

	public function update_post($post_id, $args) {
		static $is_syncing = false;

		// Prevent recursion
		if ($is_syncing) {
			return 0; // or return early without updating
		}

		$is_syncing = true;

		$args['ID'] = $post_id;
		$post_updated = wp_update_post($args, true);

		$is_syncing = false;

		if ( is_wp_error( $post_updated ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log(
					sprintf(
						__( 'Failed to update post during sync: %s', 'wp-flashnotes' ),
						$post_updated->get_error_message()
					)
				);
			}
			return 0;
		}

		return $post_updated;
	}


	private function remove_invalid_relationships( int $post_id, array $parsed_objects ): void {
		$blocks_in_post = array();

		foreach ( $parsed_objects as $block ) {
			if ( ! empty( $block['attrs']['block_id'] ) ) {
				$blocks_in_post[] = $block['attrs']['block_id'];
			}
		}

		// Select all blocks in post_id in the usage table
		$items_in_db = $this->usage->get_relationships_by_column( 'post_id', $post_id );

		foreach ( $items_in_db as $item ) {
			if ( ! in_array( $item['block_id'], $blocks_in_post ) ) {
				$this->usage->detach(
					$item['object_type'],
					$item['object_id'],
					$item['post_id'],
					$item['block_id']
				);

				$this->maybe_tag_as_orphan( $item );
			}
		}
	}

	private function maybe_tag_as_orphan( $block ): void {
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

	/**
	 * Revive an orphaned card only if reinserted via an inserter block.
	 *
	 * @param int    $child_id      The DB row ID of the card inside inserter.
	 * @param string $parent_block_name.
	 */
	private function maybe_reactivate_card( int $child_id, string $parent_block_name ): void {
		// We only revive from inserter
		if ( $parent_block_name !== 'inserter' ) {
			return;
		}

		$current = $this->cards->read( $child_id );

		if ( $current && $current['status'] === 'orphan' ) {
			$this->cards->update( $child_id, array( 'status' => 'active' ) );
		}
	}
}
