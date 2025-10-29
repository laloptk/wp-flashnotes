<?php
namespace WPFlashNotes\BaseClasses;

class DbDeltaStrategy {

	public function install(BaseTable $table): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = $table->define_schema(); // expects SQL string
		if (! is_string($sql) || trim($sql) === '') {
			throw new \RuntimeException(get_class($table) . '::define_schema() must return a SQL string for DbDeltaStrategy.');
		}

		dbDelta($sql);
	}
}
