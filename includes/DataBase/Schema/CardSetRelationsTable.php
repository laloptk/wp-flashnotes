<?php
namespace WPFlashNotes\DataBase\Schema;

use WPFlashNotes\BaseClasses\BaseTable;
use WPFlashNotes\Schema\TableBuilderStrategy;

defined('ABSPATH') || exit;

final class CardSetRelationsTable extends BaseTable {

	protected ?string $slug = 'wpfn_card_set_relations';

	public function __construct() {
		parent::__construct();
		$this->strategy = new TableBuilderStrategy();
	}

	public function define_schema($builder): void {
		$cards = $this->wpdb->prefix . 'wpfn_cards';
		$sets  = $this->wpdb->prefix . 'wpfn_sets';

		$builder
			->set_version('1.0.0')
			->add_column('card_id', 'BIGINT UNSIGNED NOT NULL')
			->add_column('set_id', 'BIGINT UNSIGNED NOT NULL')
			->add_primary_compound(['card_id', 'set_id'])
			->add_foreign_key('card_id', $cards, 'id', 'CASCADE', 'RESTRICT')
			->add_foreign_key('set_id', $sets, 'id', 'CASCADE', 'RESTRICT');
	}
}
