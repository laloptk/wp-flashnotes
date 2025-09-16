<?php

namespace WPFlashNotes\Blocks;

use WPFlashNotes\BaseClasses\BaseBlock;
use WPFlashNotes\Repos\CardsRepository;

final class InserterBlock extends BaseBlock {
	protected function get_block_folder_name(): string {
		return 'inserter';
	}

	public function render( $attributes, $content, $block ) {
		if ( empty( $attributes['id'] ) ) {
			return '<p>' . esc_html__( 'No card selected.', 'wp-flashnotes' ) . '</p>';
		}

		$card_id = intval( $attributes['id'] );

		$cards_repo = new CardsRepository();
		$card       = $cards_repo->read( $card_id );

		if ( ! $card ) {
			return '<p>' . esc_html__( 'Card not found.', 'wp-flashnotes' ) . '</p>';
		}

		// Assemble markup
		$markup  = $card['question'] ?? '';
		$answers = json_decode( $card['answers_json'], true ) ?: array();

		foreach ( $answers as $answer ) {
			$markup .= $answer;
		}

		$markup .= $card['explanation'] ?? '';

		// Parse and render blocks safely
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
			// If shorthand (all sides)
			if ( ! empty( $attributes['border']['width'] ) && ! empty( $attributes['border']['color'] ) ) {
				$style .= 'border:' . esc_attr( $attributes['border']['width'] ) . ' solid ' . esc_attr( $attributes['border']['color'] ) . ';';
			} else {
				// Per-side borders
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
			$card_id,
			$html
		);
	}
}
