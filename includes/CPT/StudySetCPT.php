<?php

namespace WPFlashNotes\CPT;

use WPFlashNotes\BaseClasses\BaseCPT;

class StudySetCPT extends BaseCPT {

	protected function set_type(): string {
		return 'studyset';
	}

	protected function set_singular(): string {
		return __( 'Study Set', 'wp-flashnotes' );
	}

	protected function set_plural(): string {
		return __( 'Study Sets', 'wp-flashnotes' );
	}

	protected function args(): array {
		return array(
			'labels'              => $this->labels(),
			'menu_icon'           => 'dashicons-index-card',
			'supports'            => array( 'title', 'editor', 'revisions' ),
			'show_in_rest'        => true,
			'public'              => true,
			'publicly_queryable'  => true,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'capability_type'     => 'post',
		);
	}
}
