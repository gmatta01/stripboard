<?php
/**
 * StripBoard Test Bootstrap
 *
 * @package StripBoard
 */

// Load Composer autoloader
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Load WordPress test environment
// This is provided by wp-phpunit/wp-phpunit package
$phpunit_dir = dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';

if ( ! file_exists( $phpunit_dir . '/bootstrap.php' ) ) {
    echo "Could not find wp-phpunit/wp-phpunit. Run: composer install\n";
    exit( 1 );
}

// Set up the WordPress test environment
require_once $phpunit_dir . '/bootstrap.php';

// Load the plugin
tests_add_filter(
    'muplugins_loaded',
    function () {
        require dirname( __DIR__ ) . '/stripboard.php';
    }
);
