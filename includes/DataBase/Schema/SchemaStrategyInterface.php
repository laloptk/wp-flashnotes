<?php
namespace WPFlashNotes\DataBase\Schema;

defined( 'ABSPATH' ) || exit;

use WPFlashNotes\BaseClasses\BaseTable;

/**
 * All schema strategies must be able to install a table and return success.
 */
interface SchemaStrategyInterface {
	public function install( BaseTable $table ): bool;
}
