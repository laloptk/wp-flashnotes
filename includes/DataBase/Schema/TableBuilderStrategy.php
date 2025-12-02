<?php
namespace WPFlashNotes\DataBase\Schema;

defined( 'ABSPATH' ) || exit;

use WPFlashNotes\BaseClasses\BaseTable;
use WPFlashNotes\DataBase\TableBuilder;

class TableBuilderStrategy implements SchemaStrategyInterface {

	public function install( BaseTable $table ): bool {
		$builder = new TableBuilder( $table->get_table_name() );

		$table->define_builder_schema( $builder );

		return $builder->createOrUpdate();
	}
}
