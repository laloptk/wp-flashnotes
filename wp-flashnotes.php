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

/** ----------------------------------------------------------------
 * Constants
 * --------------------------------------------------------------- */
if ( ! defined( 'WPFN_PLUGIN_FILE' ) ) {
	define( 'WPFN_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'WPFN_PLUGIN_DIR' ) ) {
	define( 'WPFN_PLUGIN_DIR', plugin_dir_path( WPFN_PLUGIN_FILE ) );
}
if ( ! defined( 'WPFN_PLUGIN_URL' ) ) {
	define( 'WPFN_PLUGIN_URL', plugin_dir_url( WPFN_PLUGIN_FILE ) );
}
if ( ! defined( 'WPFN_PLUGIN_BASENAME' ) ) {
	define( 'WPFN_PLUGIN_BASENAME', plugin_basename( WPFN_PLUGIN_FILE ) );
}
if ( ! defined( 'WPFN_VERSION' ) ) {
	define( 'WPFN_VERSION', '1.0.0' );
}

define( 'WPFN_API_VERSION', 1 ); // bump when API changes
define( 'WPFN_API_NAMESPACE', 'wpfn/v' . WPFN_API_VERSION ); // e.g. "wpfn/v1"


/** ----------------------------------------------------------------
 * Composer Autoload (REQUIRED)
 * --------------------------------------------------------------- */
$composer = WPFN_PLUGIN_DIR . 'vendor/autoload.php';

if ( ! file_exists( $composer ) ) {
	if ( is_admin() ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		add_action(
			'admin_notices',
			function () use ( $composer ) {
				?>
			<div class="notice notice-error">
				<p>
					<strong>WP FlashNotes</strong> can’t run because the Composer autoloader is missing:<br>
					<code><?php echo esc_html( $composer ); ?></code><br>
					Please run <code>composer install</code> in the plugin root directory.
				</p>
			</div>
				<?php
			}
		);

		add_action(
			'admin_init',
			function () {
				deactivate_plugins( WPFN_PLUGIN_BASENAME );
			}
		);
	}
	// Halt plugin loading.
	return;
}

require_once $composer;

/** ----------------------------------------------------------------
 * Activation hook (belt & suspenders)
 * --------------------------------------------------------------- */
register_activation_hook(
	WPFN_PLUGIN_FILE,
	function () {
		$composer = WPFN_PLUGIN_DIR . 'vendor/autoload.php';
		if ( ! file_exists( $composer ) ) {
			wp_die(
				'<p><strong>WP FlashNotes</strong> cannot be activated because the Composer autoloader is missing.</p>
			 <p>Run <code>composer install</code> in the plugin directory and try again.</p>',
				'WP FlashNotes: Missing dependency',
				array( 'back_link' => true )
			);
		}
	}
);

/** ----------------------------------------------------------------
 * Bootstraps
 * --------------------------------------------------------------- */
require_once WPFN_PLUGIN_DIR . 'includes/DataBase/Schema/bootstrap.php';
require_once WPFN_PLUGIN_DIR . 'includes/REST/bootstrap.php';
require_once WPFN_PLUGIN_DIR . 'includes/CPT/bootstrap.php';
require_once WPFN_PLUGIN_DIR . 'includes/Blocks/bootstrap.php';

/** ----------------------------------------------------------------
 * i18n
 * --------------------------------------------------------------- */
add_action(
	'plugins_loaded',
	function () {
		load_plugin_textdomain(
			'wp-flashnotes',
			false,
			dirname( WPFN_PLUGIN_BASENAME ) . '/languages'
		);
	}
);

/** ----------------------------------------------------------------
 * Init (services, routers, etc.)
 * --------------------------------------------------------------- */
add_action(
	'plugins_loaded',
	function () {
		// Boot service container, register controllers, etc. (later)
	},
	20
);

/** ----------------------------------------------------------------
 * Register post types / taxonomies (if any)
 * --------------------------------------------------------------- */
add_action(
	'init',
	function () {
		// register_post_type(...); register_taxonomy(...);
	}
);

/** ----------------------------------------------------------------
 * Assets
 * --------------------------------------------------------------- */
add_action(
	'enqueue_block_editor_assets',
	function () {
		wp_enqueue_script(
			'wpfn-blocks',
			plugins_url( 'build/index.js', __FILE__ ),
			array( 'wp-blocks', 'wp-element', 'wp-editor' ),
			WPFN_VERSION,
			true
		);

		wp_localize_script(
			'wpfn-blocks',
			'WPFlashNotes',
			array(
				'apiNamespace' => WPFN_API_NAMESPACE,                            // "wpfn/v1"
				'restUrl'      => esc_url_raw( rest_url( WPFN_API_NAMESPACE ) ), // "https://site/wp-json/wpfn/v1"
			)
		);
	}
);

// Example admin assets (keep commented until needed)
add_action(
	'admin_enqueue_scripts',
	function () {
		// wp_enqueue_style('wpfn-admin', WPFN_PLUGIN_URL . 'assets/css/admin.css', [], WPFN_VERSION);
		// wp_enqueue_script('wpfn-admin', WPFN_PLUGIN_URL . 'assets/js/admin.js', ['wp-element'], WPFN_VERSION, true);
	}
);

/** ----------------------------------------------------------------
 * CLI (only loads in WP-CLI context)
 * --------------------------------------------------------------- */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once WPFN_PLUGIN_DIR . 'includes/CLI/crud.php';
	require_once WPFN_PLUGIN_DIR . 'includes/CLI/testSetsCrudCommand.php';
	require_once WPFN_PLUGIN_DIR . 'includes/CLI/testCardSetRelationsCommand.php';
	require_once WPFN_PLUGIN_DIR . 'includes/CLI/testObjectUsageCommand.php';
}

add_filter( 'wp_insert_post_data', 'my_custom_pre_save_function', 10, 2 );
function my_custom_pre_save_function( $data, $postarr ) {
	// Check if it's the specific post type you want to modify
	if ( 'your_custom_post_type' === $data['post_type'] ) {
		// Modify the post title before saving

		// Add or modify custom meta data
		// This requires using update_post_meta or add_post_meta
		// after the post is actually saved, as $post_id is not available here.
	}

	error_log( 'data: ' . substr( wp_json_encode( $data['post_content'] ) /*. "Postarr: " . $postarr*/, 0, 2000 ) );

	return $data;
}
