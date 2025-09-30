<?php

use WPFlashNotes\DataBase\PropagationService;
use WPFlashNotes\Repos\CardsRepository;
use WPFlashNotes\Repos\NotesRepository;
use WPFlashNotes\Repos\SetsRepository;
use WPFlashNotes\Repos\CardSetRelationsRepository;
use WPFlashNotes\Repos\NoteSetRelationsRepository;
use WPFlashNotes\Repos\ObjectUsageRepository;
use WPFlashNotes\Blocks\Transformers\BlockTransformer;
use WPFlashNotes\Blocks\Transformers\CardBlockStrategy;
use WPFlashNotes\Helpers\BlockFormatter;

function wpfn_should_propagate( int $post_id, WP_Post $post ): bool {
	// Ignore autosaves and revisions
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return false;
	if ( wp_is_post_autosave( $post_id ) ) return false;
	if ( wp_is_post_revision( $post_id ) ) return false;
	// Extra hard gate: ignore revision post type or auto-draft status
	if ( $post->post_type === 'revision' ) return false;
	if ( in_array( $post->post_status, [ 'auto-draft', 'trash' ], true ) ) return false;
	return true;
}

function sync_studyset( $post_id, $post, $update ) {
    if ( ! wpfn_should_propagate( $post_id, $post ) ) {
        return;
    }
    $from_sidebar = did_action( 'wpfn_button_transform_context_start' ) > 0;
    $transformer = new BlockTransformer( [
        new CardBlockStrategy(),
        // add NoteBlockStrategy when ready
    ] );

    $propagation = new PropagationService(
        new CardsRepository(),
        new NotesRepository(),
        new SetsRepository(),
        new CardSetRelationsRepository(),
        new NoteSetRelationsRepository(),
        new ObjectUsageRepository()
    );

    $parsed_blocks     = BlockFormatter::parse_raw( $post->post_content );
    $flashnote_blocks  = BlockFormatter::filter_flashnotes_blocks( $parsed_blocks );
    $final_blocks = $flashnote_blocks;

    if( $from_sidebar > 0) {
        $final_blocks = $transformer->transformTree( $flashnote_blocks );
    }
    
    $normalized_blocks = BlockFormatter::normalize_to_objects( $final_blocks );

    $propagation->propagate( $post_id, $normalized_blocks );
}

function sync_post( $post_id, $post, $update ) {
    if ( ! wpfn_should_propagate( $post_id, $post ) ) {
        return;
    }

    if ( $post->post_type === 'studyset' ) {
        return;
    }

    $propagation = new PropagationService(
        new CardsRepository(),
        new NotesRepository(),
        new SetsRepository(),
        new CardSetRelationsRepository(),
        new NoteSetRelationsRepository(),
        new ObjectUsageRepository()
    );

    $parsed_blocks     = BlockFormatter::parse_raw( $post->post_content );
    $flashnote_blocks  = BlockFormatter::filter_flashnotes_blocks( $parsed_blocks );
    $normalized_blocks = BlockFormatter::normalize_to_objects( $flashnote_blocks );

    $propagation->propagate( $post_id, $normalized_blocks );
}

add_action( 'save_post', 'sync_post', 10, 3 );
add_action( 'save_post_studyset', 'sync_studyset', 10, 3 );
