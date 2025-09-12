<?php

namespace WPFlashNotes\Helpers;

defined( 'ABSPATH' ) || exit;

class BlockParser {

	/**
	 * Entry point: parse post_content and return only normalized FlashNotes blocks.
	 *
	 * @param string $content Raw post_content
	 * @return array Normalized FlashNotes blocks
	 */
	public static function from_post_content( string $content ): array {
		error_log('This is the markup normilized content: ' . json_encode($content));
		$blocks = \parse_blocks( $content );
		return self::extract_flashnotes_blocks( $blocks );
	}

	/**
	 * Extract only wpflashnotes blocks from parsed block tree.
	 *
	 * @param array $blocks Output of parse_blocks().
	 * @return array Normalized FlashNotes blocks
	 */
	private static function extract_flashnotes_blocks( array $blocks ): array {
		$results = array();

		foreach ( $blocks as $block ) {
			if ( isset( $block['blockName'] ) && str_starts_with( $block['blockName'], 'wpfn/' ) ) {
				$results[] = self::normalize_block( $block );
			}

			// Recurse into children only if innerBlocks is a non-empty array
			if ( isset($block['innerBlocks']) && ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$results = array_merge( $results, self::extract_flashnotes_blocks( $block['innerBlocks'] ) );
			}
		}

		return $results;
	}

	/**
	 * Normalize a block so we can rely on consistent keys.
	 *
	 * @param array $block Raw block array from parse_blocks().
	 * @return array Normalized block.
	 */
	private static function normalize_block( array $block ): array {
		return [
			'blockName'   => $block['blockName'] ?? '',
			'attrs'       => $block['attrs'] ?? [],
			'block_id'    => $block['attrs']['block_id'] ?? null,
			'type'        => $block['attrs']['type'] ?? null, // e.g. card subtype
			'innerBlocks' => ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) )
				? $block['innerBlocks']
				: [],
		];
	}
}
