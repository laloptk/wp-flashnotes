<?php

namespace WPFlashNotes\Helpers;

defined( 'ABSPATH' ) || exit;

class BlockFormatter {
	public static function parse_raw( string $content ): array {
		return parse_blocks( $content );
	}

	public static function serialize( array $blocks ): string {
		return serialize_blocks( $blocks );
	}

	public static function filter_flashnotes_blocks( array $blocks ): array {
		return array_values(
			array_filter(
				$blocks,
				fn( $block ) => in_array(
					$block['blockName'] ?? '',
					array( 'wpfn/note', 'wpfn/card', 'wpfn/inserter' ),
					true
				)
			)
		);
	}

	public static function normalize_to_objects( array $blocks, bool $filter_blocks = false ): array {
		if( $filter_blocks ) {
			$blocks = self::filter_flashnotes_blocks($blocks);
		}
		
		$result = array();

		foreach ( $blocks as $block ) {
			$attrs    = $block['attrs'] ?? array();
			$block_id = $attrs['block_id'] ?? null;
			if ( ! $block_id ) {
				continue;
			}

			$result[] = array(
				'object_type' => str_replace( 'wpfn/', '', $block['blockName'] ?? '' ),
				'object_id'   => $attrs['id'] ?? null,
				'block_id'    => $block_id,
				'attrs'       => $attrs,
			);
		}

		return $result;
	}
}
