<?php 

namespace WPFlashNotes\Schema;
use WPFlashNotes\BaseClasses\BaseTable;
interface SchemaStrategyInterface {
	public function install( BaseTable $table ): bool;
}