<?php
namespace WPFlashNotes\BaseClasses;

use WPFlashNotes\DataBase\TableBuilder;
use WPFlashNotes\BaseClasses\BaseTable;

class TableBuilderStrategy {

	public function install(BaseTable $table): void {
		$builder = new TableBuilder($table->get_table_name());
		$table->define_schema($builder);
		$builder->createOrUpdate();
	}
}
