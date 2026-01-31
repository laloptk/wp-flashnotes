<?php
/**
 * PHPUnit bootstrap file for WP FlashNotes.
 *
 * @package WPFlashNotes
 */

// 2) Locate the WP test suite (wordpress-tests-lib).
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// 3) Load Yoast PHPUnit polyfills early (for compatibility across WP/PHPUnit versions).
if ( ! class_exists( \Yoast\PHPUnitPolyfills\Autoload::class, false ) ) {
	$polyfills_autoload = dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
	if ( file_exists( $polyfills_autoload ) ) {
		require_once $polyfills_autoload;
	}
}

// 4) If polyfills path is provided by environment, forward it to WP test bootstrap.
$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path && '' !== $_phpunit_polyfills_path ) {
	if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
		define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
	}
}

// 5) Use LocalWP's WordPress core (important for your Local Sites setup).
if ( ! defined( 'WP_CORE_DIR' ) ) {
	$wp_core_dir = realpath( dirname( __DIR__, 4 ) . '/app/public' );
	if ( $wp_core_dir ) {
		define( 'WP_CORE_DIR', $wp_core_dir );
	}
}

// 6) Load the WP test suite functions file.
if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

require_once "{$_tests_dir}/includes/functions.php";

// 7) Load the plugin under test as a must-use plugin.
tests_add_filter(
	'muplugins_loaded',
	static function () {
		$plugin_main = dirname( __DIR__ ) . '/wp-flashnotes.php';

		// Ensure Composer autoload is available when running tests.
		$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
		if ( file_exists( $autoload ) ) {
			require_once $autoload;
		}

		$db_service = new \WPFlashNotes\Services\DatabaseService();
        $db_service->install_schema();

		require $plugin_main;
	}
);

// 8) Bootstrap the WordPress testing environment.
require "{$_tests_dir}/includes/bootstrap.php";