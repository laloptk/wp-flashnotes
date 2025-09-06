<?php

namespace WPFlashNotes\DataBase\Schema;

use WPFlashNotes\BaseClasses\BaseTable;

defined( 'ABSPATH' ) || exit;

final class NotesTable extends BaseTable {

	/**
	 * Table slug without prefix (full name will be {$wpdb->prefix}wpfn_notes).
	 *
	 * @var string|null
	 */
	protected ?string $slug = 'wpfn_notes';

	/**
	 * Returns the CREATE TABLE statement for dbDelta().
	 *
	 * @return string
	 */
	protected function get_schema(): string {
		$table  = $this->get_table_name();
		$engine = 'ENGINE=InnoDB ' . $this->get_charset_collate();

		return "
			CREATE TABLE {$table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				title VARCHAR(255) NOT NULL,
				block_id VARCHAR(128) DEFAULT NULL,
				user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				content TEXT NOT NULL,
				deleted_at DATETIME DEFAULT NULL,
				created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE KEY uq_block_id (block_id),
				KEY idx_user_id (user_id)
			) {$engine};";
	}
}
