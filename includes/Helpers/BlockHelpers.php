<?php
namespace WPFlashNotes\Helpers;

class BlockHelpers {

	/**
	 * Merge Gutenberg blocks, preserving origin structure.
	 * Replaces origin block with set block only if content differs.
	 */
	public static function merge_gutenberg_blocks( array $origin_blocks, array $set_blocks ): array {
		$indexed_set = array();
		foreach ( $set_blocks as $block ) {
			$block_id = $block['attrs']['block_id'] ?? null;
			if ( $block_id ) {
				$indexed_set[ $block_id ] = $block;
			}
		}

		$result = array();
		foreach ( $origin_blocks as $origin_block ) {
			$block_id = $origin_block['attrs']['block_id'] ?? null;
			if ( $block_id && isset( $indexed_set[ $block_id ] ) ) {
				$diff     = self::diff_block( $origin_block, $indexed_set[ $block_id ] );
				$result[] = ! empty( $diff ) ? $indexed_set[ $block_id ] : $origin_block;
			} else {
				// No match in set → keep origin
				$result[] = $origin_block;
			}
		}

		return $result;
	}

	/**
	 * Diff two Gutenberg blocks.
	 * Return the set block if content differs, empty array if same.
	 */
	public static function diff_block( array $origin_block, array $set_block ): array {
		$fields_to_check = array(
			'attrs'        => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);

		foreach ( $fields_to_check as $field => $default ) {
			if ( ( $origin_block[ $field ] ?? $default ) !== ( $set_block[ $field ] ?? $default ) ) {
				return $set_block;
			}
		}

		// Compare innerBlocks recursively
		$origin_inner = $origin_block['innerBlocks'] ?? array();
		$set_inner    = $set_block['innerBlocks'] ?? array();

		if ( count( $origin_inner ) !== count( $set_inner ) ) {
			return $set_block;
		}

		foreach ( $origin_inner as $i => $child_origin ) {
			$child_set = $set_inner[ $i ] ?? null;
			if ( ! $child_set || ! empty( self::diff_block( $child_origin, $child_set ) ) ) {
				return $set_block;
			}
		}

		return array();
	}
}
