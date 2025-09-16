<?php

namespace WPFlashNotes\Blocks;

use WPFlashNotes\BaseClasses\BaseBlock;

final class CardBlock extends BaseBlock {

	protected function get_block_folder_name(): string {
		return 'card';
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
