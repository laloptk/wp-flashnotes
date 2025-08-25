<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package WPFlashNotes
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Example: drop plugin tables
global $wpdb;

$tables = array(
	$wpdb->prefix . 'wpfn_sets',
	$wpdb->prefix . 'wpfn_cards',
	$wpdb->prefix . 'wpfn_notes',
	$wpdb->prefix . 'wpfn_card_set_relations',
	$wpdb->prefix . 'wpfn_note_set_relations',
	$wpdb->prefix . 'wpfn_taxonomy_relations',
	$wpdb->prefix . 'wpfn_object_usage',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}
