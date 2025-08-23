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

define( 'WPFN_API_VERSION',     1 );              // bump when API changes
define( 'WPFN_API_NAMESPACE',   'wpfn/v' . WPFN_API_VERSION ); // e.g. "wpfn/v1"


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
require_once WPFN_PLUGIN_DIR . 'includes/REST/bootstrap.php';
require_once WPFN_PLUGIN_DIR . 'includes/CPT/bootstrap.php';

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
add_action('admin_enqueue_scripts', 'wpfn_enqueue_admin_assets');// wp-flashnotes.php (enqueue)

add_action( 'enqueue_block_editor_assets', function () {
    wp_localize_script( 'wpfn-blocks', 'WPFlashNotes', [
        'apiNamespace' => WPFN_API_NAMESPACE,                 // "wpfn/v1"
        'restUrl'      => esc_url_raw( rest_url( WPFN_API_NAMESPACE ) ), // "https://site/wp-json/wpfn/v1"
    ] );
} );

if (defined('WP_CLI') && WP_CLI) {
    require_once WPFN_PLUGIN_DIR . 'includes/CLI/crud.php';
    require_once WPFN_PLUGIN_DIR . 'includes/CLI/testSetsCrudCommand.php';
    require_once WPFN_PLUGIN_DIR . 'includes/CLI/testCardSetRelationsCommand.php';
    require_once WPFN_PLUGIN_DIR . 'includes/CLI/testObjectUsageCommand.php';
}
require_once WPFN_PLUGIN_DIR . 'includes/Dev/crud-smoke.php';

// --- One-time seed: 30 cards + 30 notes ------------------------------------
add_action('plugins_loaded', function () {
    // Change the key if you want to run it again later.
    $flag_key = 'wpfn_seed_content_v2_done';

    if (get_option($flag_key)) {
        return; // already seeded
    }

    // Optional: only seed for admins to avoid doing this in public traffic.
    if (!is_admin()) {
        return;
    }

    // Ensure repos are loadable
    $cardsRepoClass = '\WPFlashNotes\Repos\CardsRepository';
    $notesRepoClass = '\WPFlashNotes\Repos\NotesRepository';
    if (!class_exists($cardsRepoClass) || !class_exists($notesRepoClass)) {
        error_log('[WPFlashNotes][seed] Repository classes not found. Aborting seed.');
        return;
    }

    // Ensure tables exist (cheap check + run tasks if available)
    global $wpdb;
    $needed = ['wpfn_cards', 'wpfn_notes'];
    if (function_exists('wpfn_schema_tasks')) {
        $tasks = wpfn_schema_tasks();
        foreach ($needed as $slug) {
            $table = $wpdb->prefix . $slug;
            $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($found !== $table) {
                foreach ($tasks as $t) {
                    if (($t['slug'] ?? '') === $slug) {
                        ($t['run'])();
                        break;
                    }
                }
            }
        }
    }

    $cardsRepo = new $cardsRepoClass();
    $notesRepo = new $notesRepoClass();

    // Helpers
    $uuid = function () { return function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid(); };

    $insert_card = function ($title, $answer) use ($cardsRepo) {
        // Try both payload shapes to match your repo schema.
        try {
            return $cardsRepo->insert([
                'title'  => $title,
                'answer' => $answer,
            ]);
        } catch (\Throwable $e) {
            // Fallback if repo expects 'question' instead of 'title'
            return $cardsRepo->insert([
                'question' => $title,
                'answer'   => $answer,
            ]);
        }
    };

    $insert_note = function ($title, $content) use ($notesRepo) {
        // Minimal payload based on your previous smoke test (block_id optional/omitted)
        return $notesRepo->insert([
            'title'   => $title,
            'content' => $content,
        ]);
    };

    $card_ids = [];
    $note_ids = [];
    $errors   = [];

    // Create 30 cards
    for ($i = 1; $i <= 30; $i++) {
        try {
            $id = $insert_card(
                "Seed Card {$i}",
                "Seed answer {$i}"
            );
            $card_ids[] = (int) $id;
        } catch (\Throwable $e) {
            $errors[] = "card {$i}: " . $e->getMessage();
        }
    }

    // Create 30 notes
    for ($i = 1; $i <= 30; $i++) {
        try {
            $content = '<p>Seed note ' . $i . ' — ' . esc_html(($uuid)()) . '</p>';
            $id = $insert_note(
                "Seed Note {$i}",
                $content
            );
            $note_ids[] = (int) $id;
        } catch (\Throwable $e) {
            $errors[] = "note {$i}: " . $e->getMessage();
        }
    }

    // Log summary and mark as done
    error_log('[WPFlashNotes][seed] cards=' . count($card_ids) . ' notes=' . count($note_ids) . ' errors=' . count($errors));
    if ($errors) {
        foreach ($errors as $err) {
            error_log('[WPFlashNotes][seed][error] ' . $err);
        }
    }

    update_option($flag_key, 1, false); // mark as done (autoload off)
}, 20);

