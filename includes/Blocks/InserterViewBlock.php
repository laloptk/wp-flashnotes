<?php

namespace WPFlashNotes\Blocks;

use WPFlashNotes\BaseClasses\BaseBlock;

final class InserterViewBlock extends BaseBlock {

	protected function get_block_folder_name(): string {
		return 'inserter-view';
	}

	public function render( $attributes, $content, $block ) {
		$role = $attributes['role'] ?? '';
        return sprintf(
            '<div class="wpfn-inserter-view">%s</div>',
            $content
        );
	}
}