<?php
namespace WPFlashNotes\Services;

use WPFlashNotes\Core\ServiceInterface;
use WPFlashNotes\CPT\StudySetCPT;

defined( 'ABSPATH' ) || exit;

/**
 * CptService
 *
 * Loads and registers all Custom Post Types used by WP FlashNotes.
 */
final class CptService implements ServiceInterface {

	public function register(): void {
		add_action( 'init', array( $this, 'register_cpts' ) );
	}

	public function register_cpts(): void {
		$study_set_cpt = new StudySetCPT();
		$study_set_cpt->register();
		$study_set_cpt->seedCapabilitiesToRole( 'administrator' );
	}
}
