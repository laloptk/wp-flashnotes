<?php
namespace WPFlashNotes\DataBase\Schema;

use WPFlashNotes\BaseClasses\BaseTable;
use WPFlashNotes\Schema\DbDeltaStrategy;

defined('ABSPATH') || exit;

final class NotesTable extends BaseTable {

	protected ?string $slug = 'wpfn_notes';

	public function __construct() {
		parent::__construct();
		$this->strategy = new DbDeltaStrategy();
	}

	public function define_schema(mixed $builder = null): string {
		$table   = $this->get_table_name();
		$charset = $this->get_charset_collate();

		return "
			CREATE TABLE {$table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				title VARCHAR(255) NOT NULL,
				block_id VARCHAR(128) DEFAULT NULL,
				user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				content TEXT NOT NULL,
				status ENUM('active','orphan') NOT NULL DEFAULT 'active',
				deleted_at DATETIME DEFAULT NULL,
				created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE KEY uq_block_id (block_id),
				KEY idx_user_id (user_id)
			) ENGINE=InnoDB {$charset};
		";
	}
}
