<?php
namespace WPFlashNotes\Services;

use WPFlashNotes\Core\ServiceInterface;

class I18nService implements ServiceInterface {

	public function register(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wp-flashnotes',
			false,
			dirname( WPFN_PLUGIN_BASENAME ) . '/languages'
		);
	}
}
