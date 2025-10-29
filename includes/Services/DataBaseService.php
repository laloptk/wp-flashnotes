<?php
namespace WPFlashNotes\Services;

use WPFlashNotes\Core\ServiceInterface;
use WPFlashNotes\DataBase\Schema\{
	CardsTable,
	NotesTable,
	SetsTable,
	CardSetRelationsTable,
	NoteSetRelationsTable,
	TaxonomyRelationsTable,
	ObjectUsageTable
};

defined('ABSPATH') || exit;

/**
 * DatabaseService
 *
 * Handles database schema creation at plugin activation.
 * Automatically discovers all table classes.
 */
final class DatabaseService implements ServiceInterface {

	public function register(): void {
		// Schema is only installed during plugin activation.
	}

	/**
	 * Called by register_activation_hook().
	 */
	public static function install_schema(): void {
		try {
			$tables = [
				new CardsTable(),
				new NotesTable(),
				new SetsTable(),
				new CardSetRelationsTable(),
				new NoteSetRelationsTable(),
				new TaxonomyRelationsTable(),
				new ObjectUsageTable(),
			];

			foreach ($tables as $table) {
				$table->install_table();
			}

			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('[WPFlashNotes] All database tables installed successfully.');
			}
		} catch (\Throwable $e) {
			error_log('[WPFlashNotes] Schema installation failed: ' . $e->getMessage());
		}
	}
}
