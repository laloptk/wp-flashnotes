<?php
namespace WPFlashNotes\Blocks\Transformers\Strategies;

use WPFlashNotes\Interfaces\BlockTransformStrategy;

class CardBlockStrategy implements BlockTransformStrategy {

    public function supports(array $block): bool {
        return $block['blockName'] === 'wpfn/card';
    }

    public function transform(array $block): array {
        $attrs = $block['attrs'] ?? [];

        return [
            'blockName'    => 'wpfn/inserter',
            'attrs'        => [
                'id'       => null,
                'block_id' => $attrs['block_id'] ?? null,
                'card_block_id' => $attrs['block_id']
            ],
            'innerBlocks'  => [],
            'innerHTML'    => '',
            'innerContent' => [],
        ];
    }
}