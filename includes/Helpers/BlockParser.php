<?php

namespace WPFlashNotes\Helpers;

defined( 'ABSPATH' ) || exit;

class BlockParser {
	/**
	 * Raw parse of all blocks in a post (WordPress native structure).
	 */
	public static function parse_raw( string $content ): array {
		return parse_blocks( $content );
	}

	/**
	 * Filter only flashnote blocks, keep original WP block structure
	 * (needed for serialize_blocks).
	 */
	public static function filter_flashnote_blocks( array $blocks ): array {
		return array_values(
			array_filter(
				$blocks,
				fn( $block ) => in_array(
					$block['blockName'],
					array( 'wpfn/note', 'wpfn/card', 'wpfn/inserter' ),
					true
				)
			)
		);
	}

	/**
	 * Normalize into plugin’s internal objects (used for sync/orphan logic).
	 */
	public static function normalize_to_objects( array $blocks ): array {
		$result = array();

		foreach ( $blocks as $block ) {
			$attrs    = $block['attrs'] ?? array();
			$block_id = $attrs['block_id'] ?? null;

			if ( ! $block_id ) {
				continue;
			}

			$result[] = array(
				'object_type' => str_replace( 'wpfn/', '', $block['blockName'] ),
				'object_id'   => $attrs['id'] ?? null,
				'block_id'    => $block_id,
				'attrs'       => $attrs,
			);
		}

		return $result;
	}

	/**
	 * Convenience: get flashnote objects directly from post_content.
	 */
	public static function from_post_content( string $content ): array {
		$all_blocks       = self::parse_raw( $content );
		$flashnote_blocks = self::filter_flashnote_blocks( $all_blocks );
		return self::normalize_to_objects( $flashnote_blocks );
	}
}
