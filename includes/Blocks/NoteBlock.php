<?php

namespace WPFlashNotes\Blocks;

use WPFlashNotes\BaseClasses\BaseBlock;

final class NoteBlock extends BaseBlock
{
    protected function get_block_folder_name(): string
    {
        // Change to 'notes' if your folder is named that.
        return 'note';
    }

    protected function render($attributes, $content, $block)
    {
        // Placeholder output — replace with real render later.
        return '<div class="wpfn-note-placeholder">NotesBlock placeholder</div>';
    }
}
