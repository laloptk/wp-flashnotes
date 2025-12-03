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
}
