<?php

namespace WPFlashNotes\Blocks;

use WPFlashNotes\BaseClasses\BaseBlock;

final class CardBlock extends BaseBlock
{
    protected function get_block_folder_name(): string
    {
        return 'card';
    }

    protected function render($attributes, $content, $block)
    {
        // Placeholder output — replace with real render later.
        return '<div class="wpfn-card-placeholder">CardBlock placeholder</div>';
    }
}
