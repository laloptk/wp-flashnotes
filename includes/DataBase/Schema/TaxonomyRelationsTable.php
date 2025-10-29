<?php
namespace WPFlashNotes\DataBase\Schema;

use WPFlashNotes\BaseClasses\BaseTable;
use WPFlashNotes\Schema\TableBuilderStrategy;

defined('ABSPATH') || exit;

final class TaxonomyRelationsTable extends BaseTable {

	protected ?string $slug = 'wpfn_taxonomy_relations';

	public function __construct() {
		parent::__construct();
		$this->strategy = new TableBuilderStrategy();
	}

	public function define_schema($builder): void {
		$term_taxonomy = $this->wpdb->prefix . 'term_taxonomy';

		$builder
			->set_version('1.0.0')
			->add_column('object_type', "ENUM('set','card','note') NOT NULL")
			->add_column('object_id', 'BIGINT UNSIGNED NOT NULL')
			->add_column('term_taxonomy_id', 'BIGINT UNSIGNED NOT NULL')
			->add_primary_compound(['object_type', 'object_id', 'term_taxonomy_id'])
			->add_foreign_key('term_taxonomy_id', $term_taxonomy, 'term_taxonomy_id', 'CASCADE', 'RESTRICT');
	}
}
