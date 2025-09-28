<?php
namespace WPFlashNotes\Blocks;

class BlockTransformer {

    /** @var BlockTransformStrategy[] */
    private array $strategies;

    public function __construct(array $strategies) {
        $this->strategies = $strategies;
    }

    /**
     * Transform a block tree.
     */
    public function transformTree(array $blocks): array {
        $result = [];

        foreach ($blocks as $block) {
            $result[] = $this->transformBlock($block);
        }

        return $result;
    }

    /**
     * Transform a single block, recurse into innerBlocks.
     */
    private function transformBlock(array $block): array {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($block)) {
                $block = $strategy->transform($block);
                break;
            }
        }

        // Recurse into innerBlocks
        if (!empty($block['innerBlocks'])) {
            $block['innerBlocks'] = $this->transformTree($block['innerBlocks']);
        }

        return $block;
    }
}
