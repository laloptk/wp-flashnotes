<?php
namespace WPFlashNotes\Blocks\Transformers;

use WPFlashNotes\Interfaces\BlockTransformStrategy;

class CardBlockStrategy implements BlockTransformStrategy {
    public function supports( array $block ): bool {
        return ($block['blockName'] ?? null) === 'wpfn/card';
    }

    public function transform( array $block ): array {
        $attrs = $block['attrs'] ?? [];

        return [
            'blockName'    => 'wpfn/inserter',
            'attrs'        => [
                'object_type'   => 'card',
                'id'            => $attrs['id']        ?? null, // may be null now; resolved in propagation
                'block_id'      => $attrs['block_id']  ?? null, // inserter's own identifier (can reuse)
                'card_block_id' => $attrs['block_id']  ?? null, // origin card block_id reference
            ],
            'innerBlocks'  => [],
            'innerHTML'    => '',
            'innerContent' => [],
        ];
    }
}
