<?php
/**
 * StripBoard Test Bootstrap
 *
 * @package StripBoard
 */

// Determine correct test suite path.
if ( ! defined( 'WP_TESTS_DIR' ) ) {
    define( 'WP_TESTS_DIR', '/tmp/wordpress-tests-lib' );
}

// Warn if WordPress test files not found.
if ( ! file_exists( WP_TESTS_DIR . '/includes/functions.php' ) ) {
    echo "Could not find " . WP_TESTS_DIR . '/includes/functions.php' . "\n";
    exit( 1 );
}

// Set path to PHPUnit Polyfills for WordPress test suite compatibility
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
    $vendor_autoload = WP_TESTS_DIR . '/vendor/autoload.php';
    if ( file_exists( $vendor_autoload ) ) {
        require_once $vendor_autoload;
    }
    $polyfills_path = WP_TESTS_DIR . '/vendor/yoast/phpunit-polyfills/';
    if ( file_exists( $polyfills_path ) ) {
        define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $polyfills_path );
    }
}

// Load the WP testing environment.
require_once WP_TESTS_DIR . '/includes/functions.php';

/**
 * Load the plugin under test.
 *
 * Fires on the 'muplugins_loaded' action so WordPress core is fully booted.
 */
tests_add_filter(
    'muplugins_loaded',
    function () {
        require dirname( __DIR__ ) . '/stripboard.php';
    }
);

// Start up the WP testing environment.
require WP_TESTS_DIR . '/includes/bootstrap.php';
