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
		$card = $cards_repo->read( $card_id );

		if ( ! $card ) {
			return '<p>' . esc_html__( 'Card not found.', 'wp-flashnotes' ) . '</p>';
		}

		// Assemble markup
		$markup  = $card['question'] ?? '';
		$answers = json_decode( $card['answers_json'], true ) ?: [];

		foreach ( $answers as $answer ) {
			$markup .= $answer;
		}

		$markup .= $card['explanation'] ?? '';

		// Parse and render blocks safely
		$blocks  = parse_blocks( $markup );
		$html    = '';

		foreach ( $blocks as $block_data ) {
			$html .= render_block( $block_data );
		}

		return sprintf(
			'<div class="wpfn-card" data-id="%d">%s</div>',
			$card_id,
			$html
		);
	}
}