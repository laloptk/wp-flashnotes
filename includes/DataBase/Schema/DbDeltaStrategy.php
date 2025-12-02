<?php
namespace WPFlashNotes\DataBase\Schema;

defined( 'ABSPATH' ) || exit;

use WPFlashNotes\BaseClasses\BaseTable;

class DbDeltaStrategy implements SchemaStrategyInterface {

	public function install( BaseTable $table ): bool {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = $table->define_dbdelta_schema();

		if ( ! is_string( $sql ) || trim( $sql ) === '' ) {
			throw new \RuntimeException(
				get_class( $table ) . '::define_dbdelta_schema() must return a SQL string for DbDeltaStrategy.'
			);
		}

		$result = dbDelta( $sql );

		if ( is_array( $result ) ) {
			return ! empty( $result );
		}

		return (bool) $result;
	}
}
