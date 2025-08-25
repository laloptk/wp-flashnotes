<?php
/**
 * Schema tasks registry for WP FlashNotes.
 * Each task creates/updates one table.
 */

defined( 'ABSPATH' ) || exit;

use WPFlashNotes\DataBase\Schema\CardsTable;
use WPFlashNotes\DataBase\Schema\NotesTable;
use WPFlashNotes\DataBase\TableBuilder;

/**
 * Returns the list of schema tasks with dependency order.
 * Core deps (wp_posts, wp_users, wp_term_taxonomy) are NOT listed.
 *
 * @return array<int, array{slug:string,deps:array,run:callable}>
 */
function wpfn_schema_tasks(): array {
	global $wpdb;

	return array(
		// dbDelta tables (standalone)
		array(
			'slug' => 'wpfn_cards',
			'deps' => array(),
			'run'  => function () use ( $wpdb ) {
				( new CardsTable() )->install_table();
				update_option( $wpdb->prefix . 'wpfn_cards_version', '1.0.0' );
			},
		),
		array(
			'slug' => 'wpfn_notes',
			'deps' => array(),
			'run'  => function () use ( $wpdb ) {
				( new NotesTable() )->install_table();
				update_option( $wpdb->prefix . 'wpfn_notes_version', '1.0.0' );
			},
		),

		// TableBuilder: wpfn_sets (FKs to core)
		array(
			'slug' => 'wpfn_sets',
			'deps' => array(),
			'run'  => function () use ( $wpdb ) {
				( new TableBuilder( 'wpfn_sets' ) )
					->set_version( '1.0.0' )
					->add_column( 'id', 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT' )
					->add_column( 'title', 'VARCHAR(255) NOT NULL' )
					->add_column( 'post_id', 'BIGINT UNSIGNED DEFAULT NULL' )
					->add_column( 'set_post_id', 'BIGINT UNSIGNED NOT NULL' )
					->add_column( 'user_id', 'BIGINT UNSIGNED NOT NULL' )
					->add_column( 'created_at', 'DATETIME DEFAULT CURRENT_TIMESTAMP' )
					->add_column( 'updated_at', 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP' )
					->add_primary( 'id' )
					->add_unique( 'uq_set_post_id', array( 'set_post_id' ) )
					->add_index( 'idx_post_id', array( 'post_id' ) )
					->add_foreign_key( 'set_post_id', $wpdb->prefix . 'posts', 'ID', 'CASCADE', 'RESTRICT' )
					->add_foreign_key( 'user_id', $wpdb->prefix . 'users', 'ID', 'CASCADE', 'RESTRICT' )
					->createOrUpdate();
			},
		),

		// TableBuilder: relation tables (composite PKs)
		array(
			'slug' => 'wpfn_card_set_relations',
			'deps' => array( 'wpfn_cards', 'wpfn_sets' ),
			'run'  => function () use ( $wpdb ) {
				( new TableBuilder( 'wpfn_card_set_relations' ) )
					->set_version( '1.0.0' )
					->add_column( 'card_id', 'BIGINT UNSIGNED NOT NULL' )
					->add_column( 'set_id', 'BIGINT UNSIGNED NOT NULL' )
					->add_primary_compound( array( 'card_id', 'set_id' ) )
					->add_foreign_key( 'card_id', $wpdb->prefix . 'wpfn_cards', 'id', 'CASCADE', 'RESTRICT' )
					->add_foreign_key( 'set_id', $wpdb->prefix . 'wpfn_sets', 'id', 'CASCADE', 'RESTRICT' )
					->createOrUpdate();
			},
		),
		array(
			'slug' => 'wpfn_note_set_relations',
			'deps' => array( 'wpfn_notes', 'wpfn_sets' ),
			'run'  => function () use ( $wpdb ) {
				( new TableBuilder( 'wpfn_note_set_relations' ) )
					->set_version( '1.0.0' )
					->add_column( 'note_id', 'BIGINT UNSIGNED NOT NULL' )
					->add_column( 'set_id', 'BIGINT UNSIGNED NOT NULL' )
					->add_primary_compound( array( 'note_id', 'set_id' ) )
					->add_foreign_key( 'note_id', $wpdb->prefix . 'wpfn_notes', 'id', 'CASCADE', 'RESTRICT' )
					->add_foreign_key( 'set_id', $wpdb->prefix . 'wpfn_sets', 'id', 'CASCADE', 'RESTRICT' )
					->createOrUpdate();
			},
		),

		// TableBuilder: taxonomy relations (FK to core term_taxonomy)
		array(
			'slug' => 'wpfn_taxonomy_relations',
			'deps' => array(),
			'run'  => function () use ( $wpdb ) {
				( new TableBuilder( 'wpfn_taxonomy_relations' ) )
					->set_version( '1.0.0' )
					->add_column( 'object_type', "ENUM('set','card','note') NOT NULL" )
					->add_column( 'object_id', 'BIGINT UNSIGNED NOT NULL' )
					->add_column( 'term_taxonomy_id', 'BIGINT UNSIGNED NOT NULL' )
					->add_primary_compound( array( 'object_type', 'object_id', 'term_taxonomy_id' ) )
					->add_foreign_key( 'term_taxonomy_id', $wpdb->prefix . 'term_taxonomy', 'term_taxonomy_id', 'CASCADE', 'RESTRICT' )
					->createOrUpdate();
			},
		),

		// TableBuilder: object usage (FK to core posts)
		array(
			'slug' => 'wpfn_object_usage',
			'deps' => array(),
			'run'  => function () use ( $wpdb ) {
				( new TableBuilder( 'wpfn_object_usage' ) )
					->set_version( '1.0.0' )
					->add_column( 'object_type', "ENUM('card','note') NOT NULL" )
					->add_column( 'object_id', 'BIGINT UNSIGNED NOT NULL' )
					->add_column( 'post_id', 'BIGINT UNSIGNED NOT NULL' )
					->add_column( 'block_id', 'VARCHAR(128) NOT NULL' )
					->add_primary_compound( array( 'object_type', 'object_id', 'post_id', 'block_id' ) )
					->add_foreign_key( 'post_id', $wpdb->prefix . 'posts', 'ID', 'CASCADE', 'RESTRICT' )
					->createOrUpdate();
			},
		),
	);
}
