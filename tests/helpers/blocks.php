<?php
// tests/helpers/blocks.php

use WPFlashNotes\Helpers\BlockFormatter;
use WPFlashNotes\Blocks\Transformers\BlockTransformer;
use WPFlashNotes\Blocks\Transformers\CardBlockStrategy;

defined( 'ABSPATH' ) || exit;

/**
 * Generic block factory in the same structure returned by parse_blocks().
 */
function wpfn_block( string $block_name, array $attrs = array(), array $inner_blocks = array() ): array {
	return array(
		'blockName'    => $block_name,
		'attrs'        => $attrs,
		'innerBlocks'  => $inner_blocks,
		'innerHTML'    => '',
		'innerContent' => array(),
	);
}

/**
 * Serialize an array of blocks into post_content using the plugin helper.
 */
function wpfn_serialize_blocks( array $blocks ): string {
	return BlockFormatter::serialize( $blocks );
}

/**
 * Base attrs every wpfn card should have.
 */
function wpfn_card_base_attrs( array $overrides = array() ): array {
	$defaults = array(
		'block_id' => 'block_' . wp_generate_uuid4(),
		// Some blocks might have 'id' (object id) later; keep it optional.
		'id'       => null,
	);

	return array_merge( $defaults, $overrides );
}

/**
 * Generic card block builder: type is the suffix after "wpfn/card-".
 *
 * Examples:
 * - wpfn_card_block( 'flip', [ ... ] ) => blockName "wpfn/card-flip"
 * - wpfn_card_block( 'multiplechoice', [ ... ] ) => "wpfn/card-multiplechoice"
 */
function wpfn_card_block( string $type_raw, array $attrs = array(), array $inner_blocks = array() ): array {
	$block_name = 'wpfn/card-' . $type_raw;

	$attrs = wpfn_card_base_attrs( $attrs );

	return wpfn_block( $block_name, $attrs, $inner_blocks );
}

/**
 * Specific card helpers (use these in tests for readability).
 * Add more as you need.
 */

function wpfn_card_flip_block( array $overrides = array() ): array {
	$defaults = array(
		'card_type'    => 'flip', // if your block uses this attr; harmless if ignored
		'question'     => 'Q?',
		'answers'      => array( 'A' ),
		'explanation'  => '',
	);

	$attrs = array_merge( $defaults, $overrides );

	return wpfn_card_block( 'flip', $attrs );
}

function wpfn_card_truefalse_block( array $overrides = array() ): array {
	$defaults = array(
		'card_type'   => 'truefalse',
		'statement'   => 'Statement?',
		'answer'      => true,
		'explanation' => '',
	);

	$attrs = array_merge( $defaults, $overrides );

	return wpfn_card_block( 'truefalse', $attrs );
}

function wpfn_card_multiplechoice_block( array $overrides = array() ): array {
	$defaults = array(
		'card_type'   => 'multiplechoice',
		'question'    => 'Question?',
		'choices'     => array( 'A', 'B', 'C', 'D' ),
		'answers'     => array( 'A' ),
		'explanation' => '',
	);

	$attrs = array_merge( $defaults, $overrides );

	return wpfn_card_block( 'multiplechoice', $attrs );
}

function wpfn_card_multipleselect_block( array $overrides = array() ): array {
	$defaults = array(
		'card_type'   => 'multipleselect',
		'question'    => 'Question?',
		'choices'     => array( 'A', 'B', 'C', 'D' ),
		'answers'     => array( 'A', 'C' ),
		'explanation' => '',
	);

	$attrs = array_merge( $defaults, $overrides );

	return wpfn_card_block( 'multipleselect', $attrs );
}

function wpfn_card_fillinblank_block( array $overrides = array() ): array {
	$defaults = array(
		'card_type'   => 'fillinblank',
		'prompt'      => 'The capital of France is ____.',
		'answers'     => array( 'Paris' ),
		'explanation' => '',
	);

	$attrs = array_merge( $defaults, $overrides );

	return wpfn_card_block( 'fillinblank', $attrs );
}

/**
 * Convenience: build post_content with a single card.
 */
function wpfn_post_content_with_one_card( array $card_block ): string {
	return wpfn_serialize_blocks( array( $card_block ) );
}

/**
 * Convenience: build post_content with multiple cards.
 */
function wpfn_post_content_with_cards( array $card_blocks ): string {
	return wpfn_serialize_blocks( $card_blocks );
}

/**
 * Convert card blocks into inserter blocks by simulating the "origin" pipeline.
 *
 * This mimics how cards coming from an origin source are transformed
 * into wpfn/inserter blocks in production.
 *
 * @param array $blocks Parsed block array.
 * @return array Transformed blocks (meta stripped).
 */
function wpfn_transform_cards_to_inserters( array $blocks ): array {
	$transformer = new BlockTransformer(
		[
			new CardBlockStrategy(),
		]
	);

	// Only tag card blocks as coming from origin.
	$tagged_blocks = array_map(
		static function ( array $block ): array {
			$name = $block['blockName'] ?? '';

			if ( is_string( $name ) && str_starts_with( $name, 'wpfn/card-' ) ) {
				$block['meta'] = is_array( $block['meta'] ?? null ) ? $block['meta'] : [];
				$block['meta']['source'] = 'origin';
			}

			return $block;
		},
		$blocks
	);

	return $transformer->transformTree( $tagged_blocks );
}
