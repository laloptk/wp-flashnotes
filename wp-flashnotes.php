<?php
/**
 * Plugin Name:       WP FlashNotes
 * Description:       Flashcards and notes inside WordPress.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Your Name
 * Text Domain:       wp-flashnotes
 */

defined( 'ABSPATH' ) || exit;

use WPFlashNotes\Core\Plugin;

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

register_activation_hook(
	__FILE__,
	function () {
		require_once __DIR__ . '/includes/DataBase/Schema/bootstrap.php';
	}
);

add_action( 'plugins_loaded', array( Plugin::instance(), 'init' ) );
