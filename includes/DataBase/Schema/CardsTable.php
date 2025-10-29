<?php

namespace WPFlashNotes\DataBase\Schema;

defined( 'ABSPATH' ) || exit;

use WPFlashNotes\BaseClasses\BaseTable;
use WPFlashNotes\Schema\DbDeltaStrategy;

/**
 * CardsTable
 *
 * Defines the wpfn_cards table schema using dbDelta().
 */
class CardsTable extends BaseTable {

	protected ?string $slug = 'wpfn_cards';

	public function __construct() {
		parent::__construct();
		$this->strategy = new DbDeltaStrategy();
	}

	/**
	 * Returns the CREATE TABLE statement for dbDelta().
	 *
	 * @return string
	 */
	public function define_schema(mixed $builder = null): mixed {
		$table   = $this->get_table_name();
		$charset = $this->get_charset_collate();

		return "
			CREATE TABLE {$table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				block_id VARCHAR(128) DEFAULT NULL,
				user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				question TEXT NOT NULL,
				answers_json TEXT DEFAULT NULL,
				right_answers_json TEXT DEFAULT NULL,
				explanation TEXT DEFAULT NULL,
				card_type ENUM(
					'flip',
					'true_false',
					'multiple_choice',
					'multiple_select',
					'fill_in_blank'
				) NOT NULL DEFAULT 'flip',
				status ENUM('active', 'orphan') NOT NULL DEFAULT 'active',
				deleted_at DATETIME DEFAULT NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE KEY uq_block_id (block_id),
				KEY idx_user_id (user_id),
				KEY idx_card_type (card_type)
			) ENGINE=InnoDB {$charset};
		";
	}
}