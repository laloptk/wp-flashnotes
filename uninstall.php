<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package WPFlashNotes
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

/**
 * ----------------------------------------------------------------
 * 1. Drop custom tables
 * ----------------------------------------------------------------
 */
$tables = [
    $wpdb->prefix . 'wpfn_sets',
    $wpdb->prefix . 'wpfn_cards',
    $wpdb->prefix . 'wpfn_notes',
    $wpdb->prefix . 'wpfn_card_set_relations',
    $wpdb->prefix . 'wpfn_note_set_relations',
    $wpdb->prefix . 'wpfn_taxonomy_relations',
    $wpdb->prefix . 'wpfn_object_usage',
];

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * ----------------------------------------------------------------
 * 2. Delete options & transients
 * ----------------------------------------------------------------
 *
 * Replace with the actual option/transient names you create
 * during plugin lifecycle. Example stubs below:
 */
$options = [
    'wpfn_version',
    'wpfn_settings',
];

foreach ( $options as $option ) {
    delete_option( $option );
    delete_site_option( $option ); // multisite safety
}

$transients = [
    'wpfn_cache_sets',
    'wpfn_cache_cards',
];

foreach ( $transients as $transient ) {
    delete_transient( $transient );
    delete_site_transient( $transient );
}

/**
 * ----------------------------------------------------------------
 * 3. Intentionally preserved data
 * ----------------------------------------------------------------
 *
 * For transparency with WordPress.org reviewers:
 * - User-created flashcards, notes, and sets are stored in custom DB tables.
 *   They are **removed** above when uninstalling.
 * - No personal user data is preserved.
 *
 * If you decide to keep anything (e.g., study history, analytics),
 * you must document that clearly in the plugin's README and admin settings,
 * and only preserve it with explicit user consent.
 */
