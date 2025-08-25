<?php
/**
 * Bootstrap file for registering CPTs and other initializations.
 * This file should be required from the main plugin file.
 */

use WPFlashNotes\CPT\StudySetCPT;

// Instantiate CPTs
$study_set_cpt = new StudySetCPT();

// Hook registration
add_action(
	'init',
	function () use ( $study_set_cpt ): void {
		$study_set_cpt->register();
		$study_set_cpt->seedCapabilitiesToRole( 'administrator' );
	}
);
