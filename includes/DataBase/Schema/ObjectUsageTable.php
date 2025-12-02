<?php
namespace WPFlashNotes\DataBase\Schema;

use WPFlashNotes\BaseClasses\BaseTable;
use WPFlashNotes\DataBase\TableBuilder;
use WPFlashNotes\DataBase\Schema\TableBuilderStrategy;

defined('ABSPATH') || exit;

final class ObjectUsageTable extends BaseTable {

	protected ?string $slug = 'wpfn_object_usage';

	public function __construct() {
		parent::__construct();
		$this->strategy = new TableBuilderStrategy();
	}

	public function define_builder_schema(TableBuilder $builder): void {
		$posts = $this->wpdb->prefix . 'posts';

		$builder
			->set_version('1.0.0')
			->add_column('object_type', "ENUM('card','note','inserter') NOT NULL")
			->add_column('object_id', 'BIGINT UNSIGNED NOT NULL')
			->add_column('post_id', 'BIGINT UNSIGNED NOT NULL')
			->add_column('block_id', 'VARCHAR(128) NOT NULL')
			->add_primary_compound(['object_type', 'object_id', 'post_id', 'block_id'])
			->add_foreign_key('post_id', $posts, 'ID', 'CASCADE', 'RESTRICT');
	}
}
