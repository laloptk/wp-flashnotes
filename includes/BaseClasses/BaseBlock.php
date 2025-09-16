<?php

namespace WPFlashNotes\BaseClasses;

abstract class BaseBlock {


	abstract protected function get_block_folder_name(): string;

	abstract public function render( $attributes, $content, $block );

	protected function get_blocks_slug(): string {
		return 'build/blocks/';
	}

	protected function get_block_meta_filename(): string {
		return 'block.json';
	}

	protected function get_block_pathfile(): string {
		$blocks_folder_slug = $this->get_blocks_slug();
		$block_folder_name  = $this->get_block_folder_name();
		$filename           = $this->get_block_meta_filename();
		$filepath           = WPFN_PLUGIN_DIR . $blocks_folder_slug . $block_folder_name . '/' . $filename;

		if ( ! file_exists( $filepath ) ) {
			throw new \RuntimeException( "Missing block.json at: {$filepath}" );
		}

		return $filepath;
	}

	public function register(): void {
		$args = $this->get_args();

		if ( empty( $args ) || ! array_key_exists( 'render_callback', $args ) ) {
			$args['render_callback'] = array( $this, 'render' );
		}

		$block_filepath = $this->get_block_pathfile();

		register_block_type_from_metadata( $block_filepath, $args );
	}

	protected function get_args(): array {
		return array();
	}
}
