<?php

namespace WPFlashNotes\Blocks;

use WPFlashNotes\BaseClasses\BaseBlock;

final class SlotBlock extends BaseBlock {

	protected function get_block_folder_name(): string {
		return 'slot';
	}

	public function render( $attributes, $content, $block ) {
		$role = $attributes['role'] ?? '';
        return sprintf(
            '<div class="wpfn-slot role-%s">%s</div>',
            esc_attr($role),
            $content // pass through children!
        );
	}
}