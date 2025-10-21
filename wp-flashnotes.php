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

// Delete me, this is only for development
add_filter( 'wp_is_application_passwords_available', '__return_true' );

// Load Composer autoloader first if present.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Activation hook must be registered here
register_activation_hook(
	__FILE__,
	function () {
		require_once __DIR__ . '/includes/DataBase/Schema/bootstrap.php';
	}
);

// Normal bootstrap continues after activation
add_action(
	'plugins_loaded',
	function () {
		( new Plugin() )->init();
	}
);

add_action(
	'enqueue_block_editor_assets',
	function () {
		wp_enqueue_script(
			'wp-flashnotes-sidebar',
			WPFN_PLUGIN_URL . 'build/editor-sidebar.js',
			array(
				'wp-plugins',
				'wp-editor',
				'wp-components',
				'wp-element',
				'wp-i18n',
			),
			'1.0.0',
			true
		);
	}
);

add_filter(
	'rest_pre_insert_studyset',
	function ( $prepared_post, $request ) {
		// Defensive: only touch post_content if present
		if ( ! empty( $prepared_post->post_content ) ) {
			$blocks   = \WPFlashNotes\Helpers\BlockFormatter::parse_raw( $prepared_post->post_content );
			$filtered = \WPFlashNotes\Helpers\BlockFormatter::filter_flashnotes_blocks( $blocks );

			// If you want to persist only flashnote blocks
			$prepared_post->post_content = \WPFlashNotes\Helpers\BlockFormatter::serialize( $filtered );
		}

		return $prepared_post;
	},
	10,
	3
);
