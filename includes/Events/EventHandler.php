<?php

namespace WPFlashNotes\Events;

use WP_Post;
use WPFlashNotes\DataBase\PropagationService;
use WPFlashNotes\Helpers\BlockFormatter;
use WPFlashNotes\Blocks\Transformers\BlockTransformer;
use WPFlashNotes\Blocks\Transformers\CardBlockStrategy;

class EventHandler {
	private PropagationService $propagation;
	private BlockTransformer $transformer;

	public function __construct( PropagationService $propagation ) {
		$this->propagation = $propagation;

		// Strategies are UI/domain-level, safe to instantiate here (no repos in handlers)
		$this->transformer = new BlockTransformer( [
			new CardBlockStrategy(),
			// add NoteBlockStrategy when ready
		] );
	}

	public function register(): void {
		add_action( 'save_post', [ $this, 'on_save_non_studyset' ], 10, 3 );
		add_action( 'save_post_studyset', [ $this, 'on_save_studyset' ], 10, 3 );
		// deletions will be added later
	}

	public function on_save_non_studyset( int $post_id, WP_Post $post, bool $update ): void {
		
		if ( $post->post_type === 'studyset' || $this->is_auto_generated_post($post_id) ) {
			return;
		}

		$parsed_blocks     = BlockFormatter::parse_raw( $post->post_content );
		$flashnote_blocks  = BlockFormatter::filter_flashnotes_blocks( $parsed_blocks );
		$normalized_blocks = BlockFormatter::normalize_to_objects( $flashnote_blocks );

		$this->propagation->propagate( $post_id, $normalized_blocks );
	}

	public function on_save_studyset( int $post_id, WP_Post $post, bool $update ): void {
		if ( $this->is_auto_generated_post($post_id) ) {
			return;
		}

		if( did_action( 'wpfn_button_transform_context_start' ) > 0 ) {
			return;
		}

		$parsed_blocks     = BlockFormatter::parse_raw( $post->post_content );
		$flashnote_blocks  = BlockFormatter::filter_flashnotes_blocks( $parsed_blocks );
		$normalized_blocks = BlockFormatter::normalize_to_objects( $flashnote_blocks );

		$this->propagation->propagate( $post_id, $normalized_blocks );
	}

	/**
	 * Button/API flow – generate/update a studyset from an origin post.
	 * Transforms happen only here. wp_insert_post/wp_update_post will trigger on_save_studyset → propagate.
	 */
	public function generate_studyset_from_origin( int $origin_post_id, string $title, int $author_id, string $status = 'publish' ): array {
		do_action( 'wpfn_button_transform_context_start' );
		
		$origin_post = get_post( $origin_post_id );
		
		if ( ! $origin_post ) {
			return [
				'ok'    => false,
				'reason'=> 'missing_origin',
			];
		}

		$parsed_blocks    = BlockFormatter::parse_raw( $origin_post->post_content );
		$flashnote_blocks = BlockFormatter::filter_flashnotes_blocks( $parsed_blocks );
		$transformed_blocks = $this->transformer->transformTree( $flashnote_blocks );
		error_log("This comes from the event handler, it is the transformed blocks: " . json_encode($transformed_blocks));
		$normalized_blocks = BlockFormatter::normalize_to_objects($transformed_blocks);
		$serialized_content = BlockFormatter::serialize( $transformed_blocks );
		error_log("This comes from the event handler, it is the serialized content: " . json_encode($serialized_content));

		$existing_set_id = $this->propagation->get_studyset_for_origin_post( $origin_post_id );

		if ( $existing_set_id ) {
			wp_update_post( [
				'ID'           => (int) $existing_set_id,
				'post_content' => $serialized_content,
			] );

			$this->propagation->register_post_set_relation([
				'title'          => $title,
				'origin_post_id' => $origin_post_id,
				'set_post_id'    => (int) $existing_set_id,
				'user_id'        => $author_id,
			]);

			$this->propagation->propagate($existing_set_id, $normalized_blocks);

			return [
				'ok'          => true,
				'studyset_id' => (int) $existing_set_id,
				'action'      => 'updated',
			];
		}

		$studyset_id = wp_insert_post( [
			'post_type'    => 'studyset',
			'post_title'   => $title,
			'post_status'  => $status,
			'post_author'  => $author_id,
			'post_content' => $serialized_content,
		] );

		$this->propagation->register_post_set_relation( [
			'title' => $title,
			'origin_post_id' => $origin_post_id,
			'set_post_id'    => (int) $studyset_id,
			'user_id' => $author_id,
		]);

		$this->propagation->propagate($studyset_id, $normalized_blocks);

		return [
			'ok'          => true,
			'studyset_id' => (int) $studyset_id,
			'action'      => 'created',
		];
	}

	protected function is_auto_generated_post($post_id) {
		// Ignore autosaves and revisions
		if ( 
			defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ||
			wp_is_post_autosave( $post_id ) ||
			wp_is_post_revision( $post_id )
		) {
			return true;
		}

		return false;
	}
}
