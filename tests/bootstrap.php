<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Rsscloud
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Exception used to capture rsscloud_notify_result() calls in tests
 * instead of calling exit().
 */
class RsscloudNotifyResultException extends Exception {
	public $success;
	public $msg;

	public function __construct( $success, $msg ) {
		$this->success = $success;
		$this->msg     = $msg;
		parent::__construct( "notify_result: success=$success msg=$msg" );
	}
}

/**
 * Test-friendly override of rsscloud_notify_result() that throws
 * instead of calling exit.
 */
function rsscloud_notify_result( $success, $msg ) {
	throw new RsscloudNotifyResultException( $success, $msg );
}

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( __DIR__, 1 ) . '/rsscloud/rsscloud.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";
