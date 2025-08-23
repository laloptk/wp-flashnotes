<?php
/**
 * Bootstrap file for registering CPTs and other initializations.
 * This file should be required from the main plugin file.
 */

use WPFlashNotes\Blocks\NoteBlock;

//Instantiate CPTs
$note_block = new NoteBlock();

// Hook registration
add_action( 'init', function () use ( $note_block ): void {
    $note_block->register();
});