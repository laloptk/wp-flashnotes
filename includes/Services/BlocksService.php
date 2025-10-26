<?php
namespace WPFlashNotes\Services;

use WPFlashNotes\Core\ServiceInterface;
use WPFlashNotes\Blocks\NoteBlock;
use WPFlashNotes\Blocks\CardBlock;
use WPFlashNotes\Blocks\SlotBlock;
use WPFlashNotes\Blocks\InserterBlock;

defined( 'ABSPATH' ) || exit;

/**
 * BlocksService
 *
 * Handles registration of all Gutenberg blocks used by WP FlashNotes.
 * Replaces includes/Blocks/bootstrap.php.
 */
final class BlocksService implements ServiceInterface {

	public function register(): void {
		add_action( 'init', [ $this, 'register_blocks' ] );
	}

	/**
	 * Instantiate and register all plugin blocks.
	 */
	public function register_blocks(): void {
		$blocks = [
			new NoteBlock(),
			new CardBlock(),
			new SlotBlock(),
			new InserterBlock(),
		];

		foreach ( $blocks as $block ) {
			if ( method_exists( $block, 'register' ) ) {
				$block->register();
			}
		}
	}
}
