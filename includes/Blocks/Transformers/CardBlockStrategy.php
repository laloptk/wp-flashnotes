<?php
namespace WPFlashNotes\Blocks\Transformers;

use WPFlashNotes\Interfaces\BlockTransformStrategy;

class CardBlockStrategy implements BlockTransformStrategy {
	public function supports( array $block ): bool {
		return ( $block['blockName'] ?? null ) === 'wpfn/card';
	}

	public function transform( array $block ): array {
		$attrs             = $block['attrs'] ?? array();
		$origin_block_id   = $attrs['block_id'] ?? null;          // card's block_id (from origin post)
		$inserter_block_id = wp_generate_uuid4();                 // new block_id for the inserter
		$card_id           = $attrs['id'] ?? null;                // may be null until propagation

		// Exact placeholder markup that your save() emits (no inline styles).
		$placeholder = sprintf(
			'<div class="wp-block-wpfn-inserter wpfn-card" data-id="%s" data-block-id="%s"></div>',
			esc_attr( $card_id ?? '' ),
			esc_attr( $inserter_block_id )
		);

		return array(
			'blockName'    => 'wpfn/inserter',
			'attrs'        => array(
				'object_type'   => 'card',
				'id'            => $card_id,
				'block_id'      => $inserter_block_id,
				'card_block_id' => $origin_block_id,
			),
			'innerBlocks'  => array(),
			'innerHTML'    => $placeholder,
			'innerContent' => array( $placeholder ),
		);
	}
}
