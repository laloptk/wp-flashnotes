<?php

namespace WPFlashNotes\Blocks;

use WPFlashNotes\BaseClasses\BaseBlock;

final class NoteBlock extends BaseBlock {

	protected function get_block_folder_name(): string {
		return 'note';
	}

	public function render( $attributes, $content, $block ) {
		// Placeholder output — replace with real render later.
		return array();
	}

	protected function get_args(): array {
		return array(
			'render_callback' => null,
		);
	}
}
