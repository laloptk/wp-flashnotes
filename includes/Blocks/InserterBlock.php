<?php

namespace WPFlashNotes\Blocks;

use WPFlashNotes\BaseClasses\BaseBlock;

final class InserterBlock extends BaseBlock {

	protected function get_block_folder_name(): string {
		return 'inserter';
	}

	public function render( $attributes, $content, $block ) {
        return '<div>Placeholder</div>';
	}
}