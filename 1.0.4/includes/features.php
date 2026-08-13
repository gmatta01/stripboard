<?php
/**
 * Feature disabling methods for StripBoard
 * 
 *  Stripboard
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Feature disabling methods (to be added to main class)
 */
trait Stripboard_Features {
    
    /**
     * Disable Gutenberg completely
     */
    public function disable_gutenberg() {
        // Disable Gutenberg for posts
        add_filter('use_block_editor_for_post', '__return_false');
        
        // Disable Gutenberg for all post types
        add_filter('use_block_editor_for_post_type', '__return_false');
        
        // Remove Gutenberg CSS and JS
        add_action('wp_enqueue_scripts', function() {
            wp_dequeue_style('wp-block-library');
            wp_dequeue_style('wp-block-library-theme'); 
            wp_dequeue_style('wc-block-style'); // WooCommerce
            wp_dequeue_style('global-styles');
        }, 100);
        
        add_action('admin_enqueue_scripts', function() {
            wp_dequeue_script('wp-editor');
            wp_dequeue_style('wp-editor');
        }, 100);
        
        // Remove block editor from widgets
        add_filter('use_widgets_block_editor', '__return_false');
        
        // Remove block patterns
        remove_theme_support('core-block-patterns');
        
        // Remove block editor features
        add_action('after_setup_theme', function() {
            remove_theme_support('block-templates');
            remove_theme_support('block-template-parts');
        });
        
        // Disable REST API endpoints for blocks
        add_filter('rest_endpoints', function($endpoints) {
            if (isset($endpoints['/wp/v2/blocks'])) {
                unset($endpoints['/wp/v2/blocks']);
            }
            if (isset($endpoints['/wp/v2/block-types'])) {
                unset($endpoints['/wp/v2/block-types']);
            }
            return $endpoints;
        });
    }
    
    /**
     * Disable classic editor features
     */
    public function disable_classic_editor() {
        // Remove TinyMCE
        add_filter('tiny_mce_plugins', '__return_empty_array');
        
        // Remove classic editor toolbar
        add_filter('mce_buttons', '__return_empty_array');
        add_filter('mce_buttons_2', '__return_empty_array');
        
        // Remove media buttons
        remove_action('media_buttons', 'media_buttons');
        
        // Remove quicktags
        add_filter('quicktags_settings', '__return_empty_array');
        
        // Remove visual/text tabs
        add_filter('wp_editor_settings', function($settings) {
            $settings['quicktags'] = false;
            $settings['tinymce'] = false;
            return $settings;
        });
    }
    
    /**
     * Disable comments system
     */
    public function disable_comments() {
        // Close comments on the front-end
        add_filter('comments_open', '__return_false', 20, 2);
        add_filter('pings_open', '__return_false', 20, 2);
        
        // Hide existing comments
        add_filter('comments_array', '__return_empty_array', 10, 2);
        
        // Remove comments page in menu
        add_action('admin_menu', function() {
            remove_menu_page('edit-comments.php');
        });
        
        // Remove comments links from admin bar
        add_action('init', function() {
            if (is_admin_bar_showing()) {
                remove_action('admin_bar_menu', 'wp_admin_bar_comments_menu', 60);
            }
        });
        
        // Remove comments from post and pages
        add_action('admin_init', function() {
            remove_meta_box('commentstatusdiv', 'post', 'normal');
            remove_meta_box('commentsdiv', 'post', 'normal');
            remove_meta_box('trackbacksdiv', 'post', 'normal');
            remove_meta_box('commentstatusdiv', 'page', 'normal');
            remove_meta_box('trackbacksdiv', 'page', 'normal');
        });
        
        // Remove comments column from posts list
        add_filter('manage_posts_columns', function($columns) {
            unset($columns['comments']);
            return $columns;
        });
        
        add_filter('manage_pages_columns', function($columns) {
            unset($columns['comments']);
            return $columns;
        });
        
        // Remove comment-reply script for themes that include it
        add_action('wp_enqueue_scripts', function() {
            wp_deregister_script('comment-reply');
        }, 100);
        
        // Remove recent comments widget
        add_action('widgets_init', function() {
            unregister_widget('WP_Widget_Recent_Comments');
        });
        
        // Remove X-Pingback HTTP header
        add_filter('wp_headers', function($headers) {
            unset($headers['X-Pingback']);
            return $headers;
        });
    }
    
    /**
     * Disable pingbacks and trackbacks
     */
    public function disable_pingbacks() {
        // Disable pingback processing
        add_filter('xmlrpc_methods', function($methods) {
            unset($methods['pingback.ping']);
            unset($methods['pingback.extensions.getPingbacks']);
            return $methods;
        });
        
        // Remove X-Pingback HTTP header
        add_filter('wp_headers', function($headers) {
            unset($headers['X-Pingback']);
            return $headers;
        });
        
        // Disable pingbacks on posts
        add_filter('pings_open', '__return_false');
        
        // Remove pingback URL from HTML head
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wlwmanifest_link');
        
        // Close pingbacks for existing posts
        add_action('pre_ping', function(&$links) {
            $links = array();
        });
        
        // Remove trackback rewrite rules
        add_filter('rewrite_rules_array', function($rules) {
            foreach ($rules as $rule => $rewrite) {
                if (preg_match('/trackback\/\?\$/', $rule)) {
                    unset($rules[$rule]);
                }
            }
            return $rules;
        });
    }
    
    /**
     * Disable feeds
     */
    public function disable_feeds() {
        wp_die(
            esc_html__('Feeds have been disabled.', 'stripboard'),
            esc_html__('Feeds Disabled', 'stripboard'),
            array('response' => 403)
        );
    }
    
    /**
     * Disable embeds
     */
    public function disable_embeds() {
        // Remove the REST API endpoint
        remove_action('rest_api_init', 'wp_oembed_register_route');
        
        // Turn off oEmbed auto discovery
        add_filter('embed_oembed_discover', '__return_false');
        
        // Don't filter oEmbed results
        remove_filter('oembed_dataparse', 'wp_filter_oembed_result', 10);
        
        // Remove oEmbed discovery links
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        
        // Remove oEmbed-specific JavaScript from the front-end and back-end
        remove_action('wp_head', 'wp_oembed_add_host_js');
        
        // Remove all embeds rewrite rules
        add_filter('rewrite_rules_array', function($rules) {
            foreach ($rules as $rule => $rewrite) {
                if (false !== strpos($rewrite, 'embed=true')) {
                    unset($rules[$rule]);
                }
            }
            return $rules;
        });
        
        // Remove filter of the oEmbed result before any HTTP requests are made
        remove_filter('pre_oembed_result', 'wp_filter_pre_oembed_result', 10);
        
        // Remove wp-embed.min.js
        add_action('wp_footer', function() {
            wp_deregister_script('wp-embed');
        });
        
        // Remove the embed query var
        add_filter('query_vars', function($vars) {
            $key = array_search('embed', $vars);
            if (false !== $key) {
                unset($vars[$key]);
            }
            return $vars;
        });
        
        // Remove embeds from TinyMCE
        add_filter('tiny_mce_plugins', function($plugins) {
            return array_diff($plugins, array('wpembed'));
        });
        
        // Disable oEmbed for posts
        add_filter('embed_post_id', '__return_false');
    }
    
    /**
     * Disable emoji
     */
    public function disable_emoji() {
        // Remove emoji CDN hostname from DNS prefetching hints.
        // Avoid apply_filters( 'emoji_svg_url' ) — that core hook name trips PrefixAllGlobals.
        add_filter('wp_resource_hints', function($urls, $relation_type) {
            if ('dns-prefetch' !== $relation_type || !is_array($urls)) {
                return $urls;
            }

            return array_values(array_filter($urls, function($url) {
                return is_string($url) && false === strpos($url, 's.w.org/images/core/emoji');
            }));
        }, 10, 2);
        
        // Remove emoji scripts and styles
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        
        // Remove emoji from TinyMCE
        add_filter('tiny_mce_plugins', function($plugins) {
            if (is_array($plugins)) {
                return array_diff($plugins, array('wpemoji'));
            } else {
                return array();
            }
        });
        
        // Remove emoji CDN hostname from DNS prefetching hints
        add_filter('emoji_svg_url', '__return_false');
    }
    
    /**
     * Disable heartbeat
     */
    public function disable_heartbeat() {
        // Deregister the heartbeat scripts
        add_action('wp_enqueue_scripts', function() {
            wp_deregister_script('heartbeat');
        });
        
        add_action('admin_enqueue_scripts', function() {
            wp_deregister_script('heartbeat');
        });
        
        // Hook into the heartbeat settings
        add_filter('heartbeat_settings', function($settings) {
            $settings['autostart'] = false;
            return $settings;
        });
        
        // Remove heartbeat from admin screens where it's not needed
        add_action('admin_init', function() {
            wp_deregister_script('heartbeat');
        }, 1);
    }
    
    /**
     * Disable author archives
     */
    public function disable_author_archives() {
        if (is_author()) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            nocache_headers();
            wp_die('', 404);
        }
    }
    
    /**
     * Disable search
     */
    public function disable_search($query) {
        if (!is_admin() && $query->is_main_query()) {
            if ($query->is_search()) {
                $query->set_404();
                status_header(404);
            }
        }
    }
    
    /**
     * Disable date archives
     */
    public function disable_date_archives() {
        if (is_date()) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            nocache_headers();
            wp_die('', 404);
        }
    }

    /**
     * Disable media attachment pages
     */
    public function disable_attachment_pages() {
        if (is_attachment()) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            nocache_headers();
            wp_die('', 404);
        }
    }
    
    /**
     * Get feature status for display
     */
    public function get_feature_status($feature_key) {
        $settings = $this->get_settings();
        $feature_data = isset($this->features[$feature_key]) ? $this->features[$feature_key] : null;
        
        if (!$feature_data) {
            return null;
        }
        
        $is_enabled = isset($settings[$feature_key]) ? $settings[$feature_key] : $feature_data['default'];
        
        return array(
            'enabled' => $is_enabled,
            'name' => $feature_data['name'],
            'description' => $feature_data['description'],
            'category' => $feature_data['category']
        );
    }
    
    /**
     * Check if a specific feature is enabled
     *
     * @param string $feature_key Feature slug.
     * @return bool|null True/false for registered features; null if the key is unknown.
     */
    public function is_feature_enabled($feature_key) {
        if (empty($this->features) && did_action('init')) {
            $this->boot_features();
        }

        if (!isset($this->features[$feature_key])) {
            return null;
        }

        $settings = $this->get_settings();
        $feature_data = $this->features[$feature_key];

        return isset($settings[$feature_key]) ? (bool) $settings[$feature_key] : (bool) $feature_data['default'];
    }
    
    /**
     * Get all disabled features
     */
    public function get_disabled_features() {
        $settings = $this->get_settings();
        $disabled = array();
        
        foreach ($this->features as $feature_key => $feature_data) {
            $is_enabled = isset($settings[$feature_key]) ? $settings[$feature_key] : $feature_data['default'];
            if (!$is_enabled) {
                $disabled[] = $feature_key;
            }
        }
        
        return $disabled;
    }
    
    /**
     * Reset all features to defaults
     */
    public function reset_to_defaults() {
        $default_settings = array();
        foreach ($this->features as $key => $feature) {
            $default_settings[$key] = $feature['default'];
        }
        return $this->update_settings($default_settings);
    }
    
    /**
     * Remove dashboard widgets
     */
    public function remove_dashboard_widgets() {
        remove_meta_box('dashboard_right_now', 'dashboard', 'normal');
        remove_meta_box('dashboard_activity', 'dashboard', 'normal');
        remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
        remove_meta_box('dashboard_incoming_links', 'dashboard', 'normal');
        remove_meta_box('dashboard_plugins', 'dashboard', 'normal');
        remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
        remove_meta_box('dashboard_recent_drafts', 'dashboard', 'side');
        remove_meta_box('dashboard_primary', 'dashboard', 'side');
        remove_meta_box('dashboard_secondary', 'dashboard', 'side');
    }
    

    
    /**
     * Remove Google Fonts from enqueued styles where possible.
     */
    public function remove_google_fonts() {
        global $wp_styles;

        if (!($wp_styles instanceof WP_Styles) || empty($wp_styles->registered)) {
            return;
        }

        foreach ($wp_styles->registered as $handle => $style) {
            if (empty($style->src) || !is_string($style->src)) {
                continue;
            }

            if (strpos($style->src, 'fonts.googleapis.com') !== false || strpos($style->src, 'fonts.gstatic.com') !== false) {
                wp_dequeue_style($handle);
                wp_deregister_style($handle);
            }
        }
    }

    /**
     * Disable WooCommerce frontend assets outside core commerce pages.
     */
    public function conditionally_disable_woocommerce_assets() {
        if (is_admin()) {
            return;
        }

        if (!function_exists('is_woocommerce')) {
            return;
        }

        $is_store_context = is_woocommerce() || is_cart() || is_checkout() || is_account_page();
        if ($is_store_context) {
            return;
        }

        $styles = array(
            'woocommerce-layout',
            'woocommerce-general',
            'woocommerce-smallscreen',
            'wc-blocks-style',
            'wc-blocks-vendors-style',
        );

        foreach ($styles as $style) {
            wp_dequeue_style($style);
        }

        $scripts = array(
            'woocommerce',
            'wc-add-to-cart',
            'wc-cart-fragments',
            'wc-checkout',
            'wc-add-to-cart-variation',
            'js-cookie',
        );

        foreach ($scripts as $script) {
            wp_dequeue_script($script);
        }
    }
    
    /**
     * Disable user enumeration
     */
    public function disable_user_enumeration() {
        // Block author parameter in URLs
        add_action('init', function() {
            // Public ?author= query var — redirect only; no nonce on front-end requests.
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if (!isset($_GET['author']) || is_admin()) {
                return;
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $author = sanitize_text_field(wp_unslash($_GET['author']));
            if ('' !== $author) {
                wp_safe_redirect(home_url('/'), 301);
                exit;
            }
        });
        
        // Remove user info from REST API
        add_filter('rest_endpoints', function($endpoints) {
            if (isset($endpoints['/wp/v2/users'])) {
                unset($endpoints['/wp/v2/users']);
            }
            return $endpoints;
        });
        
        // Disable author archives
        add_action('template_redirect', function() {
            if (is_author()) {
                wp_safe_redirect(home_url('/'), 301);
                exit;
            }
        });
    }

    /**
     * Remove query strings from static file URLs for improved proxy/CDN cache hit rates.
     *
     * @param string $src Asset URL.
     * @return string
     */
    public function remove_query_strings_from_src( $src ) {
        if ( strpos( $src, '?ver=' ) !== false ) {
            $src = remove_query_arg( 'ver', $src );
        }
        return $src;
    }
}
