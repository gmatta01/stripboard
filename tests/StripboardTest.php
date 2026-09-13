<?php
/**
 * StripBoard PHP Tests
 *
 * Tests PHP code without WordPress or MySQL dependencies.
 *
 * @package Stripboard
 */

use PHPUnit\Framework\TestCase;

class StripboardTest extends TestCase {

    /**
     * Test that PHP is working
     */
    public function test_php_is_working() {
        $this->assertTrue( true );
    }

    /**
     * Test string operations
     */
    public function test_string_operations() {
        $plugin_name = 'stripboard';
        $this->assertEquals( 'Stripboard', ucfirst( $plugin_name ) );
    }

    /**
     * Test array operations
     */
    public function test_array_operations() {
        $features = array( 'gutenberg', 'comments', 'pingbacks' );
        $this->assertCount( 3, $features );
        $this->assertContains( 'gutenberg', $features );
    }

    /**
     * Test that plugin file exists and has content
     */
    public function test_plugin_file_exists() {
        $plugin_file = dirname( __DIR__ ) . '/stripboard.php';
        $this->assertFileExists( $plugin_file );
        
        $content = file_get_contents( $plugin_file );
        $this->assertStringContainsString( 'Stripboard', $content );
    }
}
