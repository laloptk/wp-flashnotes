<?php
namespace WPFlashNotes\Services;

use WPFlashNotes\Core\ServiceInterface;
use WPFlashNotes\Blocks\NoteBlock;
use WPFlashNotes\Blocks\CardBlock;
use WPFlashNotes\Blocks\SlotBlock;
use WPFlashNotes\Blocks\InserterBlock;
use WPFlashNotes\Blocks\NoteInserterBlock;

defined( 'ABSPATH' ) || exit;

/**
 * BlocksService
 *
 * Handles registration of all Gutenberg blocks used by WP FlashNotes.
 * Replaces includes/Blocks/bootstrap.php.
 */
final class BlocksService implements ServiceInterface {

	public function register(): void {
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Instantiate and register all plugin blocks.
	 */
	public function register_blocks(): void {
		$blocks = array(
			new NoteBlock(),
			new CardBlock(),
			new SlotBlock(),
			new InserterBlock(),
            new NoteInserterBlock(),
		);

		foreach ( $blocks as $block ) {
			if ( method_exists( $block, 'register' ) ) {
				$block->register();
			}
		}
	}
}
