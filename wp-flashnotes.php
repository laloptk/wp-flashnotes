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

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

use WPFlashNotes\Services\DataBaseService;
use WPFlashNotes\Core\Plugin;

register_activation_hook(
	__FILE__,
	array( DataBaseService::class, 'install_schema' )
);

add_action( 'plugins_loaded', array( Plugin::instance(), 'init' ) );
