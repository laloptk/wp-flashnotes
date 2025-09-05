<?php
/**
 * Bootstrap file for registering CPTs and other initializations.
 * This file should be required from the main plugin file.
 */

use WPFlashNotes\Blocks\CardBlock;
use WPFlashNotes\Blocks\NoteBlock;
use WPFlashNotes\Blocks\SlotBlock;
use WPFlashNotes\Blocks\InserterBlock;

// Instantiate CPTs
$blocks_to_register = [
	new NoteBlock(),
	new CardBlock(),
	new SlotBlock(),
	new InserterBlock(),
];

// Hook registration
add_action(
	'init',
	function () use ( $blocks_to_register ): void {
		foreach($blocks_to_register as $block) {
			$block->register();
		}
	}
);
