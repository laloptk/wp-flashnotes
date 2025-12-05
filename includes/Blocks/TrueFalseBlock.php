<?php

namespace WPFlashNotes\Blocks;

use WPFlashNotes\BaseClasses\BaseBlock;

class TrueFalseBlock extends BaseBlock {

	protected function get_block_folder_name(): string {
		return 'true-false-card';
	}

	public function render( $attributes, $content, $block ) {
		return array();
	}

	protected function get_args(): array {
		return array(
			'render_callback' => null,
		);
	}
}
