<?php
namespace WPFlashNotes\DataBase\Schema;

use WPFlashNotes\BaseClasses\BaseTable;
use WPFlashNotes\DataBase\TableBuilder;
use WPFlashNotes\DataBase\Schema\TableBuilderStrategy;

defined('ABSPATH') || exit;

final class SetsTable extends BaseTable {

	protected ?string $slug = 'wpfn_sets';

	public function __construct() {
		parent::__construct();
		$this->strategy = new TableBuilderStrategy();
	}

	public function define_builder_schema(TableBuilder $builder): void {
        $posts_table = $this->wpdb->prefix . 'posts';
		$users_table = $this->wpdb->prefix . 'users';

		$builder
			->set_version('1.0.0')
			->add_column('id', 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT')
			->add_column('title', 'VARCHAR(255) NOT NULL')
			->add_column('post_id', 'BIGINT UNSIGNED DEFAULT NULL')
			->add_column('set_post_id', 'BIGINT UNSIGNED NOT NULL')
			->add_column('user_id', 'BIGINT UNSIGNED NOT NULL')
			->add_column('created_at', 'DATETIME DEFAULT CURRENT_TIMESTAMP')
			->add_column('updated_at', 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP')
			->add_primary('id')
			->add_unique('uq_set_post_id', ['set_post_id'])
			->add_index('idx_post_id', ['post_id'])
			->add_foreign_key('set_post_id', $posts_table, 'ID', 'CASCADE', 'RESTRICT')
			->add_foreign_key('user_id', $users_table, 'ID', 'CASCADE', 'RESTRICT');
	}
}
