<?php

namespace WPFlashNotes\Events;

use WP_Post;
use WPFlashNotes\DataBase\PropagationService;
use WPFlashNotes\Helpers\BlockFormatter;
use WPFlashNotes\Blocks\Transformers\BlockTransformer;
use WPFlashNotes\Blocks\Transformers\CardBlockStrategy;

/**
 * Handles WordPress lifecycle events and studyset generation logic.
 */
class EventHandler {
	private PropagationService $propagation;
	private BlockTransformer $transformer;

	public function __construct( PropagationService $propagation ) {
		$this->propagation = $propagation;

		// Strategies are UI/domain-level; safe to instantiate directly here.
		$this->transformer = new BlockTransformer(
			array(
				new CardBlockStrategy(),
			// Add NoteBlockStrategy when ready.
			)
		);
	}

	public function register(): void {
		add_action( 'save_post', array( $this, 'on_save_non_studyset' ), 10, 3 );
		add_action( 'save_post_studyset', array( $this, 'on_save_studyset' ), 10, 3 );
		// Deletion hooks will be added later.
	}

	public function on_save_non_studyset( int $post_id, WP_Post $post, bool $update ): void {
		if ( $post->post_type === 'studyset' || $this->is_auto_generated_post( $post_id ) ) {
			return;
		}

		$parsed_blocks     = BlockFormatter::parse_raw( $post->post_content );
		$flashnote_blocks  = BlockFormatter::filter_flashnotes_blocks( $parsed_blocks );
		$normalized_blocks = BlockFormatter::normalize_to_objects( $flashnote_blocks );

		$this->propagation->propagate( $post_id, $normalized_blocks );
	}

	public function on_save_studyset( int $post_id, WP_Post $post, bool $update ): void {
		if ( $this->is_auto_generated_post( $post_id ) ) {
			return;
		}

		// Prevent recursion during manual button syncs.
		if ( did_action( 'wpfn_button_transform_context_start' ) > 0 ) {
			return;
		}

		$parsed_blocks     = BlockFormatter::parse_raw( $post->post_content );
		$flashnote_blocks  = BlockFormatter::filter_flashnotes_blocks( $parsed_blocks );
		$normalized_blocks = BlockFormatter::normalize_to_objects( $flashnote_blocks );

		$this->propagation->propagate( $post_id, $normalized_blocks );
	}

	/**
	 * Button/API flow – generate or update a studyset from an origin post.
	 * Transforms happen only here. wp_insert_post/wp_update_post will
	 * trigger on_save_studyset → propagate.
	 *
	 * @param int    $origin_post_id Origin post ID.
	 * @param string $title          Optional title (computed in controller).
	 * @param int    $author_id      User ID performing the action.
	 * @param string $status         Post status for new studysets.
	 * @return array                 Result payload for REST response.
	 */
	public function generate_studyset_from_origin( int $origin_post_id, string $title, int $author_id, string $status = 'publish' ): array {
		do_action( 'wpfn_button_transform_context_start' );

		$origin_post = get_post( $origin_post_id );

		if ( ! $origin_post ) {
			return array(
				'ok'     => false,
				'reason' => 'missing_origin',
			);
		}

		// Ensure title is never empty.
		$title = $title ?: get_the_title( $origin_post );

		$parsed_blocks      = BlockFormatter::parse_raw( $origin_post->post_content );
		$flashnote_blocks   = BlockFormatter::filter_flashnotes_blocks( $parsed_blocks );
		$transformed_blocks = $this->transformer->transformTree( $flashnote_blocks );
		$normalized_blocks  = BlockFormatter::normalize_to_objects( $transformed_blocks );
		$serialized_content = BlockFormatter::serialize( $transformed_blocks );

		$existing_set_id = $this->propagation->get_studyset_for_origin_post( $origin_post_id );

		if ( $existing_set_id ) {
			// Studyset exists – preserve its title.
			$existing_set_post = get_post( $existing_set_id );
			$studyset_title    = $existing_set_post ? $existing_set_post->post_title : get_the_title( $origin_post );

			wp_update_post(
				array(
					'ID'           => (int) $existing_set_id,
					'post_content' => $serialized_content,
				)
			);

			$this->propagation->register_post_set_relation(
				array(
					'title'       => $studyset_title,
					'post_id'     => $origin_post_id,
					'set_post_id' => (int) $existing_set_id,
					'user_id'     => $author_id,
				)
			);

			$this->propagation->propagate( $existing_set_id, $normalized_blocks );

			return array(
				'ok'          => true,
				'studyset_id' => (int) $existing_set_id,
				'action'      => 'updated',
			);
		}

		// Studyset does not exist – create it using origin post title.
		$studyset_title = get_the_title( $origin_post );

		$studyset_id = wp_insert_post(
			array(
				'post_type'    => 'studyset',
				'post_title'   => $studyset_title,
				'post_status'  => $status,
				'post_author'  => $author_id,
				'post_content' => $serialized_content,
			)
		);

		$this->propagation->register_post_set_relation(
			array(
				'title'       => $studyset_title,
				'post_id'     => $origin_post_id,
				'set_post_id' => (int) $studyset_id,
				'user_id'     => $author_id,
			)
		);

		$this->propagation->propagate( $studyset_id, $normalized_blocks );

		return array(
			'ok'          => true,
			'studyset_id' => (int) $studyset_id,
			'action'      => 'created',
		);
	}

	protected function is_auto_generated_post( int $post_id ): bool {
		// Ignore autosaves and revisions.
		if (
			( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ||
			wp_is_post_autosave( $post_id ) ||
			wp_is_post_revision( $post_id )
		) {
			return true;
		}

		return false;
	}
}
