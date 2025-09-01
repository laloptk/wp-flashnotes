<?php
/**
 * PHPUnit bootstrap file for WP FlashNotes.
 *
 * @package WP_FlashNotes
 */

// Allow override via environment.
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Load PHPUnit Polyfills before WP bootstrap.
if ( ! class_exists( \Yoast\PHPUnitPolyfills\Autoload::class ) ) {
	$polyfills_autoload = dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
	if ( file_exists( $polyfills_autoload ) ) {
		require_once $polyfills_autoload;
	}
}

// Forward custom PHPUnit Polyfills configuration to WP bootstrap if set.
$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// >>> THIS IS THE IMPORTANT PART <<<
// Force the test suite to use Local's WordPress core instead of /tmp/wordpress.
if ( ! defined( 'WP_CORE_DIR' ) ) {
	define( 'WP_CORE_DIR', realpath( dirname( __DIR__, 4 ) . '/app/public' ) );
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( __DIR__ ) . '/wp-flashnotes.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";


