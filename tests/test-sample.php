<?php
/**
 * Sample Test for StripBoard
 *
 * @package StripBoard
 */

class SampleTest extends WP_UnitTestCase {

    /**
     * Test that the plugin is loaded
     */
    public function test_plugin_is_loaded() {
        $this->assertTrue( class_exists( 'Stripboard' ) );
    }

    /**
     * Test that the plugin has features
     */
    public function test_plugin_has_features() {
        $instance = Stripboard::get_instance();
        $this->assertIsArray( $instance->features );
    }
}
