<?php
/**
 * Plugin Name: WP FlashNotes
 * Description: Manage notes and flashcards, integrated with Gutenberg and the REST API.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL-2.0-or-later
 * Text Domain: wp-flashnotes
 */

defined('ABSPATH') || exit;

/** ----------------------------------------------------------------
 * Constants
 * --------------------------------------------------------------- */
if (!defined('WPFN_PLUGIN_FILE')) {
    define('WPFN_PLUGIN_FILE', __FILE__);
}
if (!defined('WPFN_PLUGIN_DIR')) {
    define('WPFN_PLUGIN_DIR', plugin_dir_path(WPFN_PLUGIN_FILE));
}
if (!defined('WPFN_PLUGIN_URL')) {
    define('WPFN_PLUGIN_URL', plugin_dir_url(WPFN_PLUGIN_FILE));
}
if (!defined('WPFN_PLUGIN_BASENAME')) {
    define('WPFN_PLUGIN_BASENAME', plugin_basename(WPFN_PLUGIN_FILE));
}
if (!defined('WPFN_VERSION')) {
    define('WPFN_VERSION', '1.0.0');
}

/** ----------------------------------------------------------------
 * Composer Autoload (REQUIRED)
 * - If missing, show admin error, auto-deactivate, and stop loading.
 * --------------------------------------------------------------- */
$composer = WPFN_PLUGIN_DIR . 'vendor/autoload.php';

if (!file_exists($composer)) {
    if (is_admin()) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        add_action('admin_notices', function () use ($composer) {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong>WP FlashNotes</strong> can’t run because the Composer autoloader is missing:<br>
                    <code><?php echo esc_html($composer); ?></code><br>
                    Please run <code>composer install</code> in the plugin root directory.
                </p>
            </div>
            <?php
        });

        add_action('admin_init', function () {
            deactivate_plugins(WPFN_PLUGIN_BASENAME);
        });
    }
    // Halt plugin loading.
    return;
}

require_once $composer;

/** ----------------------------------------------------------------
 * Block activation if autoloader is missing (belt & suspenders)
 * --------------------------------------------------------------- */
register_activation_hook(WPFN_PLUGIN_FILE, function () {
    $composer = WPFN_PLUGIN_DIR . 'vendor/autoload.php';
    if (!file_exists($composer)) {
        wp_die(
            '<p><strong>WP FlashNotes</strong> cannot be activated because the Composer autoloader is missing.</p>
             <p>Run <code>composer install</code> in the plugin directory and try again.</p>',
            'WP FlashNotes: Missing dependency',
            ['back_link' => true]
        );
    }
});

/** ----------------------------------------------------------------
 * Schema bootstrap (registers activation hook & builds tables)
 * --------------------------------------------------------------- */
require_once WPFN_PLUGIN_DIR . 'includes/DataBase/Schema/bootstrap.php';

/** ----------------------------------------------------------------
 * i18n
 * --------------------------------------------------------------- */
function wpfn_load_textdomain(): void {
    load_plugin_textdomain(
        'wp-flashnotes',
        false,
        dirname(WPFN_PLUGIN_BASENAME) . '/languages'
    );
}
add_action('plugins_loaded', 'wpfn_load_textdomain');

/** ----------------------------------------------------------------
 * Init (services, routers, etc.)
 * --------------------------------------------------------------- */
function wpfn_init(): void {
    // Boot service container, register controllers, etc. (later)
}
add_action('plugins_loaded', 'wpfn_init', 20);

/** ----------------------------------------------------------------
 * Register post types / taxonomies (if any)
 * --------------------------------------------------------------- */
function wpfn_register_post_types(): void {
    // register_post_type(...); register_taxonomy(...);
}
add_action('init', 'wpfn_register_post_types');

/** ----------------------------------------------------------------
 * REST API routes
 * --------------------------------------------------------------- */
function wpfn_register_api_routes(): void {
    // register_rest_route(...);
}
add_action('rest_api_init', 'wpfn_register_api_routes');

/** ----------------------------------------------------------------
 * Assets
 * --------------------------------------------------------------- */
function wpfn_enqueue_front_assets(): void {
    // wp_enqueue_style('wpfn-frontend', WPFN_PLUGIN_URL . 'assets/css/frontend.css', [], WPFN_VERSION);
    // wp_enqueue_script('wpfn-frontend', WPFN_PLUGIN_URL . 'assets/js/frontend.js', ['wp-element'], WPFN_VERSION, true);
}
add_action('wp_enqueue_scripts', 'wpfn_enqueue_front_assets');

function wpfn_enqueue_admin_assets(): void {
    // wp_enqueue_style('wpfn-admin', WPFN_PLUGIN_URL . 'assets/css/admin.css', [], WPFN_VERSION);
    // wp_enqueue_script('wpfn-admin', WPFN_PLUGIN_URL . 'assets/js/admin.js', ['wp-element'], WPFN_VERSION, true);
}
add_action('admin_enqueue_scripts', 'wpfn_enqueue_admin_assets');
