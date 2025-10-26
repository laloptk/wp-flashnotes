<?php
namespace WPFlashNotes\Blocks\Transformers;

class BlockTransformer {

	private array $strategies;

	public function __construct( array $strategies ) {
		$this->strategies = $strategies;
	}

	/**
	 * Transform a block tree, only applying transformations to
	 * blocks marked as coming from the 'origin' source.
	 *
	 * @param array $blocks
	 * @return array
	 */
	public function transformTree( array $blocks ): array {
		$result = array();

		foreach ( $blocks as $block ) {
			$source = $block['meta']['source'] ?? 'unknown';

			if ( $source === 'origin' ) {
				$block = $this->transformBlock( $block );
			}

			// Always remove transient metadata before returning
			unset( $block['meta'] );

			$result[] = $block;
		}

		return $result;
	}

	/**
	 * Transform a single block using its matching strategy.
	 * If no strategy matches, return block as-is.
	 *
	 * @param array $block
	 * @return array
	 */
	private function transformBlock( array $block ): array {
		foreach ( $this->strategies as $strategy ) {
			if ( $strategy->supports( $block ) ) {
				$block = $strategy->transform( $block );
				break;
			}
		}

		return $block;
	}
}
