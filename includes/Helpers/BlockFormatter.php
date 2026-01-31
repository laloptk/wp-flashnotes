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
				function ( $block ) {
					$name = $block['blockName'] ?? '';
					// Match any wpfn/card-* blocks or specific note blocks
					return str_starts_with($name, 'wpfn/card-') 
						|| in_array($name, ['wpfn/note', 'wpfn/inserter', 'wpfn/note-inserter'], true);
				}
			)
		);
	}

	public static function normalize_to_objects( array $blocks, bool $filter_blocks = false ): array {
		if ( $filter_blocks ) {
			$blocks = self::filter_flashnotes_blocks( $blocks );
		}

		$result = array();

		foreach ( $blocks as $block ) {
			$attrs    = $block['attrs'] ?? array();
			$block_id = $attrs['block_id'] ?? null;
			if ( ! $block_id ) {
				continue;
			}

			$is_card = isset($block['blockName']) && str_starts_with($block['blockName'], 'wpfn/card-');

			$data = array(
				'object_type' => $is_card === true ? 'card' : 'note',
				'object_id'   => $attrs['id'] ?? null,
				'block_id'    => $block_id,
				'attrs'       => $attrs,
			);

			if ( $is_card === true ) {
				// Extract card type from block name: wpfn/card-flip -> flip
				$type_raw = str_replace('wpfn/card-', '', $block['blockName']);
				
				// Map block name to database ENUM format
				$type_mapping = array(
					'flip'           => 'flip',
					'truefalse'      => 'true-false',
					'multiplechoice' => 'multiple-choice',
					'multipleselect' => 'multiple-select',
					'fillinblank'    => 'fill-in-blank',
				);
				
				$data["card_type"] = $type_mapping[$type_raw] ?? $type_raw;
			}
			
			$result[] = $data;
		}

		return $result;
	}
}
