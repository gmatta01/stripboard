<?php
/**
 * Uninstall script for StripBoard
 * 
 * @package Stripboard
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Define plugin constants if not already defined
if (!defined('STRIPBOARD_VERSION')) {
    define('STRIPBOARD_VERSION', '1.0.3');
}

/**
 * Clean up plugin data on uninstall
 */
class Stripboard_Uninstall {
    
    /**
     * Run uninstall process
     */
    public static function uninstall() {
        // Remove plugin options
        delete_option('stripboard_settings');
        delete_option('disable_kit_settings');
        delete_option('wp_strip_settings');
        
        // Remove any transients
        delete_transient('stripboard_cache');
        
        // Remove user meta if any
        delete_metadata('user', 0, 'stripboard_dismissed_notices', '', true);
        
        // Flush rewrite rules to clean up any custom rules
        flush_rewrite_rules();
        
        // Clear any cached data
        wp_cache_flush();
        
        // Remove any scheduled events if any were created
        wp_clear_scheduled_hook('stripboard_cleanup');
        
        // Log uninstall if WP_DEBUG is enabled
        // (intentionally silent in production)
    }
}

// Run the uninstall process
Stripboard_Uninstall::uninstall();
