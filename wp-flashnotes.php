<?php
/**
 * Plugin Name:       WP FlashNotes
 * Plugin URI:        https://github.com/yourname/wp-flashnotes
 * Description:       Create, manage, and study flashcards and notes directly in WordPress. Built with custom DB tables and Gutenberg integration.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Your Name
 * Author URI:        https://your-portfolio-or-site.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-flashnotes
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

use WPFlashNotes\Plugin;

// Load Composer autoloader first if present.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

add_action( 'plugins_loaded', function() {
    ( new Plugin() )->init();
});
