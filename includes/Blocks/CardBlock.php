<?php

namespace WPFlashNotes\Blocks;

use WPFlashNotes\BaseClasses\BaseBlock;

final class CardBlock extends BaseBlock {

	protected function get_block_folder_name(): string {
		return 'card';
	}

	public function render( $attributes, $content, $block ) {
        $block_id = $attributes['block_id'] ?? '';
        return sprintf(
            '<div class="wpfn-card" data-id="%s">%s</div>',
            esc_attr($block_id),
            $content // children markup from post_content
        );
	}
}
