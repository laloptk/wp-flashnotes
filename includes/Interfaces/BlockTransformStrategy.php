<?php
namespace WPFlashNotes\Interfaces;

interface BlockTransformStrategy {
    /**
     * Check if this strategy supports the given block.
     */
    public function supports(array $block): bool;

    /**
     * Transform a single block into another form.
     */
    public function transform(array $block): array;
}
