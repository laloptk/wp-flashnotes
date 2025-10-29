<?php
namespace WPFlashNotes\DataBase\Schema;

use WPFlashNotes\BaseClasses\BaseTable;
use WPFlashNotes\Schema\TableBuilderStrategy;

defined('ABSPATH') || exit;

final class NoteSetRelationsTable extends BaseTable {

	protected ?string $slug = 'wpfn_note_set_relations';

	public function __construct() {
		parent::__construct();
		$this->strategy = new TableBuilderStrategy();
	}

	public function define_schema($builder): void {
		$notes = $this->wpdb->prefix . 'wpfn_notes';
		$sets  = $this->wpdb->prefix . 'wpfn_sets';

		$builder
			->set_version('1.0.0')
			->add_column('note_id', 'BIGINT UNSIGNED NOT NULL')
			->add_column('set_id', 'BIGINT UNSIGNED NOT NULL')
			->add_primary_compound(['note_id', 'set_id'])
			->add_foreign_key('note_id', $notes, 'id', 'CASCADE', 'RESTRICT')
			->add_foreign_key('set_id', $sets, 'id', 'CASCADE', 'RESTRICT');
	}
}
