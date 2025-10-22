<?php

namespace WPFlashNotes\Managers;

use WPFlashNotes\Repos\SetsRepository;

class ContextLinkManager {
	protected SetsRepository $sets;

	public function __construct( SetsRepository $sets ) {
		$this->sets = $sets;
	}

	public function register_hooks(): void {
		add_action( 'edit_form_after_title', array( $this, 'render_context_link' ) );
	}

	public function render_context_link( $post ) {
		if ( $post->post_type === 'studyset' ) {
			$set_row = $this->sets->get_by_set_post_id( $post->ID );

			if ( $set_row && (int) $set_row['post_id'] !== (int) $set_row['set_post_id'] ) {
				$origin_id = (int) $set_row['post_id'];
				$url       = get_edit_post_link( $origin_id, 'admin' );
				echo '<div class="notice notice-info inline"><p>';
				printf(
					__( 'This studyset was generated from <a href="%s">its origin post</a>.', 'wp-flashnotes' ),
					esc_url( $url )
				);
				echo '</p></div>';
			}
		}

		if ( in_array( $post->post_type, array( 'post', 'page', 'your_cpts' ), true ) ) {
			$sets_repo = $this->sets->get_by_post_id( $post->ID );
			if ( $sets_repo && isset( $sets_repo[0]['set_post_id'] ) ) {
				$studyset_id = (int) $sets_repo[0]['set_post_id'];
				$url         = get_edit_post_link( $studyset_id, 'admin' );
				echo '<div class="notice notice-info inline"><p>';
				printf(
					__( 'This post has a linked <a href="%s">studyset</a>.', 'wp-flashnotes' ),
					esc_url( $url )
				);
				echo '</p></div>';
			}
		}
	}
}
