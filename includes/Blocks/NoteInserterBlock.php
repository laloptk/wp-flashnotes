<?php

namespace WPFlashNotes\Blocks;

use WPFlashNotes\BaseClasses\BaseBlock;
use WPFlashNotes\Repos\NotesRepository;

final class NoteInserterBlock extends BaseBlock {

	protected function get_block_folder_name(): string {
		return 'note-inserter';
	}

	public function render( $attributes, $content, $block ) {
		$notes_repo = new NotesRepository();
		$note       = null;

		// Try to resolve card by ID first, fallback to block_id
		if ( ! empty( $attributes['id'] ) ) {
			$note_id = (int) $attributes['id'];
			$note    = $notes_repo->read( $note_id );
		} elseif ( ! empty( $attributes['note_block_id'] ) ) {
			$note = $notes_repo->get_by_block_id( $attributes['note_block_id'] );
		}

		if ( ! $note ) {
			return '<p>' . esc_html__( 'No note selected.', 'wp-flashnotes' ) . '</p>';
		}

		// Assemble markup
		$markup  = $note['title'] ?? '';
        $markup  .= $note['content'] ?? '';

		// Parse and render nested blocks safely
		$blocks = parse_blocks( $markup );
		$html   = '';

		foreach ( $blocks as $block_data ) {
			$html .= render_block( $block_data );
		}

		$style = '';

		// Background
		if ( ! empty( $attributes['backgroundColor'] ) ) {
			$style .= 'background-color:' . esc_attr( $attributes['backgroundColor'] ) . ';';
		}

		// Borders
		if ( ! empty( $attributes['border'] ) ) {
			if ( ! empty( $attributes['border']['width'] ) && ! empty( $attributes['border']['color'] ) ) {
				$style .= 'border:' . esc_attr( $attributes['border']['width'] ) . ' solid ' . esc_attr( $attributes['border']['color'] ) . ';';
			} else {
				foreach ( $attributes['border'] as $side => $val ) {
					if ( ! empty( $val['width'] ) && ! empty( $val['color'] ) ) {
						$style .= 'border-' . esc_attr( $side ) . ':' . esc_attr( $val['width'] ) . ' solid ' . esc_attr( $val['color'] ) . ';';
					}
				}
			}
		}

		// Margin
		if ( ! empty( $attributes['margin'] ) ) {
			foreach ( $attributes['margin'] as $side => $val ) {
				if ( $val !== '' && $val !== null ) {
					$style .= 'margin-' . esc_attr( $side ) . ':' . esc_attr( $val ) . ';';
				}
			}
		}

		// Padding
		if ( ! empty( $attributes['padding'] ) ) {
			foreach ( $attributes['padding'] as $side => $val ) {
				if ( $val !== '' && $val !== null ) {
					$style .= 'padding-' . esc_attr( $side ) . ':' . esc_attr( $val ) . ';';
				}
			}
		}

		// Border radius
		if ( ! empty( $attributes['borderRadius'] ) ) {
			foreach ( $attributes['borderRadius'] as $corner => $val ) {
				if ( $val !== '' && $val !== null ) {
					switch ( $corner ) {
						case 'top':
							$style .= 'border-top-left-radius:' . esc_attr( $val ) . ';';
							break;
						case 'right':
							$style .= 'border-top-right-radius:' . esc_attr( $val ) . ';';
							break;
						case 'bottom':
							$style .= 'border-bottom-right-radius:' . esc_attr( $val ) . ';';
							break;
						case 'left':
							$style .= 'border-bottom-left-radius:' . esc_attr( $val ) . ';';
							break;
					}
				}
			}
		}

		return sprintf(
			'<div class="wpfn-card" style="%s" data-id="%d">%s</div>',
			esc_attr( $style ),
			(int) ( $note['id'] ?? 0 ),
			$html
		);
	}
}
