<?php
/**
 * Plugin Name: StripBoard
 * Plugin URI: https://github.com/gmatta01/disable-kit
 * Description: Simply disable unwanted WordPress features from one settings board.
 * Version: 1.0.3
 * Author: GM
 * Author URI: https://github.com/gmatta01
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: stripboard
 * Domain Path: /languages
 * Requires at least: 5.9
 * Tested up to: 7.0
 * Requires PHP: 7.4
 *
 * @package Stripboard
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('STRIPBOARD_VERSION', '1.0.3');
define('STRIPBOARD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('STRIPBOARD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('STRIPBOARD_PLUGIN_FILE', __FILE__);

// Safety kill switch - can be added to wp-config.php to bypass all functionality
if (defined('STRIPBOARD_BYPASS') && STRIPBOARD_BYPASS) {
    return;
}

// Include required files
require_once STRIPBOARD_PLUGIN_DIR . 'includes/admin.php';
require_once STRIPBOARD_PLUGIN_DIR . 'includes/features.php';

/**
 * Main plugin class
 */
class Stripboard {
    
    // Include trait files
    use Stripboard_Admin;
    use Stripboard_Features;
    
    /**
     * Single instance of the plugin
     */
    private static $instance = null;
    
    /**
     * Array of manageable features
     */
    private $features = array();
    
    /**
     * Plugin options key
     */
    private $options_key = 'stripboard_settings';
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->maybe_migrate_legacy_settings();
        $this->init_hooks();

        // WP 6.7+: translations (__()) must not run before init.
        if (did_action('init')) {
            $this->boot_features();
        } else {
            add_action('init', array($this, 'boot_features'), 0);
        }
    }

    /**
     * Build the feature registry and apply disables (init or later only).
     */
    public function boot_features() {
        if (!empty($this->features)) {
            return;
        }

        $this->define_features();
        $this->init_feature_controls();
    }

    /**
     * Schedule a callback on a hook, or run it now if that hook already fired.
     *
     * @param string   $hook     Hook name.
     * @param callable $callback Callback.
     * @param int      $priority Priority.
     */
    private function run_on_hook_or_now($hook, $callback, $priority = 10) {
        if (did_action($hook)) {
            call_user_func($callback);
            return;
        }

        add_action($hook, $callback, $priority);
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Plugin lifecycle hooks
        register_activation_hook(STRIPBOARD_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(STRIPBOARD_PLUGIN_FILE, array($this, 'deactivate'));
        
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Define all manageable features
     *
     * Each feature supports:
     *   name        — Display label (plain English)
     *   description — What it does, what breaks if disabled, when to disable
     *   category    — Groups the feature in the UI
     *   risk        — 'low' | 'medium' | 'high'
     *   scope       — 'frontend' | 'admin' | 'both'
     *   default     — bool, whether enabled by default
     *   priority    — int, load order
     */
    private function define_features() {
        $this->features = array(

            // ── Writing & Content ────────────────────────────────────────────
            'gutenberg' => array(
                'name'        => __('Block Editor (Gutenberg)', 'stripboard'),
                'description' => __('The modern drag-and-drop editor for posts and pages. Disabling it reverts to the Classic Editor. Most page builders like Elementor still work without it, but the native block editor will be gone.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'high',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1,
                'children'    => array('block_widgets', 'block_directory', 'font_library', 'block_editor_assets_non_editors', 'remove_block_library_css', 'interactivity_api')
            ),
            'classic_editor' => array(
                'name'        => __('Classic Editor (TinyMCE)', 'stripboard'),
                'description' => __('The legacy text editor toolbar. Disable this only if every user on your site is comfortable with the block editor and no plugins depend on the old TinyMCE interface.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'medium',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),
            'block_widgets' => array(
                'name'        => __('Block Widgets Editor', 'stripboard'),
                'description' => __('Replaces the classic widget area with a block-based editor. Disable to restore the traditional drag-and-drop widget panel, which some plugins still rely on.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'medium',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),
            'site_editor' => array(
                'name'        => __('Full Site Editor', 'stripboard'),
                'description' => __('Allows editing your entire site layout — header, footer, templates — using blocks. Only available with block themes. Disabling removes it from the admin menu but does not break the site.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'high',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),
            'posts' => array(
                'name'        => __('Blog Posts', 'stripboard'),
                'description' => __('The core blog post content type. Disabling removes the Posts menu and all post-related pages from the admin. Only safe for sites that do not publish blog content.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'high',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1,
                'children'    => array('categories', 'tags', 'revisions', 'autosave', 'adjacent_posts_links', 'post_formats', 'capital_p_dangit', 'wptexturize', 'convert_smilies')
            ),
            'pages' => array(
                'name'        => __('Pages', 'stripboard'),
                'description' => __('Static pages (About, Contact, etc.). Disabling removes the Pages menu and all static pages from the admin. Only disable if your site is a pure single-page or application build.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'high',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1
            ),
            'attachments' => array(
                'name'        => __('Media Attachments', 'stripboard'),
                'description' => __('The media library and file uploads. Disabling removes the Media menu. Images already on your site remain, but you cannot add new ones through the admin.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'medium',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1,
                'children'    => array(
                    'attachment_pages',
                    'default_attachment_display',
                    'responsive_images',
                    'webp_uploads',
                    'pdf_thumbnails',
                    'disable_lazy_load',
                    'disable_auto_scaling_images',
                )
            ),
            'categories' => array(
                'name'        => __('Post Categories', 'stripboard'),
                'description' => __('Organises posts into groups. Disabling removes category management and category archive pages. Safe only for sites that do not use post categories at all.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'high',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1
            ),
            'tags' => array(
                'name'        => __('Post Tags', 'stripboard'),
                'description' => __('Keyword labels attached to posts. Disabling removes tag management and tag archive pages. Safe if your site does not use tags for navigation or SEO.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'medium',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1
            ),
            'comments' => array(
                'name'        => __('Comments System', 'stripboard'),
                'description' => __('Visitor comments on posts and pages. Disabling removes comment forms, the admin comments menu, and all comment data from page loads. Cannot be reversed per-post once globally disabled.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'high',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1,
                'children'    => array('comment_reply_script', 'comment_feeds', 'comment_cookies', 'comment_threading', 'comment_url_field', 'pingbacks', 'comment_avatars', 'comment_html')
            ),
            'revisions' => array(
                'name'        => __('Post Revision History', 'stripboard'),
                'description' => __('Saves a copy of your post every time you update it, allowing you to roll back changes. Disabling stops new revisions from being created. Existing revisions remain in the database.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'medium',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),
            'autosave' => array(
                'name'        => __('Auto-Save While Editing', 'stripboard'),
                'description' => __('Automatically saves a draft copy of your post every 60 seconds while you type. Disabling may cause you to lose work if your browser crashes or connection drops.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'medium',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),

            // ── Media & Embeds ───────────────────────────────────────────────
            'embeds' => array(
                'name'        => __('Automatic Link Previews (oEmbed)', 'stripboard'),
                'description' => __('Turns YouTube, Twitter, and other links into embedded previews automatically. Also lets your content be embedded on other sites. Disable if you prefer plain links and want to reduce external requests.', 'stripboard'),
                'category'    => 'media',
                'risk'        => 'medium',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1,
                'children'    => array('wp_embed_script', 'wp_mediaelement')
            ),
            'emoji' => array(
                'name'        => __('WordPress Emoji Support', 'stripboard'),
                'description' => __('Loads a JavaScript file to render emoji consistently across older browsers. Modern browsers display emoji natively, so this script is usually unnecessary and adds a small page load overhead.', 'stripboard'),
                'category'    => 'media',
                'risk'        => 'low',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1
            ),
            'gravatars' => array(
                'name'        => __('Gravatar Profile Images', 'stripboard'),
                'description' => __('Loads comment author avatars from Gravatar.com. Each avatar is an external HTTP request. Disable to remove these requests and show a default avatar instead.', 'stripboard'),
                'category'    => 'media',
                'risk'        => 'low',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1
            ),
            'dns_prefetch' => array(
                'name'        => __('Browser DNS Pre-loading', 'stripboard'),
                'description' => __('Adds a hidden tag that tells browsers to pre-resolve domain names for external services before they are needed, slightly speeding up those connections. Safe to disable if you manage your own performance hints.', 'stripboard'),
                'category'    => 'media',
                'risk'        => 'low',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'google_fonts' => array(
                'name'        => __('Google Fonts Loading', 'stripboard'),
                'description' => __('Some themes and plugins automatically load fonts from Google Fonts servers. Disable to stop these requests — useful for GDPR compliance or if you host fonts locally.', 'stripboard'),
                'category'    => 'media',
                'risk'        => 'medium',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'disable_lazy_load' => array(
                'name'        => __('Native Image Lazy Loading', 'stripboard'),
                'description' => __('WordPress automatically adds loading="lazy" to images and iframes so they only load when scrolled into view. Disable only if you are using a third-party lazy loading plugin that conflicts with it.', 'stripboard'),
                'category'    => 'media',
                'risk'        => 'medium',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1
            ),
            'disable_auto_scaling_images' => array(
                'name'        => __('Auto-Scale Oversized Images', 'stripboard'),
                'description' => __('WordPress resizes images larger than 2560px wide on upload to save storage. Disable if you need to preserve original full-resolution images exactly as uploaded.', 'stripboard'),
                'category'    => 'media',
                'risk'        => 'medium',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1
            ),

            // ── Site Speed ───────────────────────────────────────────────────
            'heartbeat' => array(
                'name'        => __('Background Auto-Sync', 'stripboard'),
                'description' => __('Sends a request to the server every 60 seconds while you have an admin page open, keeping your login session alive and enabling auto-save. Disabling reduces server load on shared hosting but stops real-time lock warnings in the editor.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'medium',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1
            ),
            'jquery_migrate' => array(
                'name'        => __('jQuery Migrate Script', 'stripboard'),
                'description' => __('A compatibility shim that allows old jQuery code written before 2012 to keep working. Safe to disable on modern sites. If anything breaks after disabling, re-enable it — an old plugin likely depends on it.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'medium',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1,
                'children'    => array('jquery_migrate_admin')
            ),
            'wp_embed_script' => array(
                'name'        => __('WordPress Embed Script', 'stripboard'),
                'description' => __('Loads a script that lets other sites embed your content as a preview card. Disable if you do not need your content to be embeddable elsewhere — saves one HTTP request.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'medium',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'comment_reply_script' => array(
                'name'        => __('Comment Reply Script', 'stripboard'),
                'description' => __('Loads a tiny script that moves the comment form below the reply you clicked. Only needed if comments are enabled. Safe to disable on sites without comments.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'low',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'admin_bar_script' => array(
                'name'        => __('Admin Bar Frontend Script', 'stripboard'),
                'description' => __('Loads a small JavaScript file on the public site to support the admin toolbar shown to logged-in users. Safe to disable if you have disabled the admin bar or do not use it.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'low',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'backbone_underscore' => array(
                'name'        => __('Legacy JavaScript Libraries', 'stripboard'),
                'description' => __('Loads Backbone.js and Underscore.js — older JavaScript libraries required by some classic WordPress features. Disable only if no plugins or themes on your site use them. If things break, re-enable.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'medium',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'wp_util_script' => array(
                'name'        => __('WordPress Helper Scripts', 'stripboard'),
                'description' => __('Loads wp-util.js, a small helper used by some WordPress AJAX features and media handling. Safe to disable on simple sites, but may break upload flows or dynamic forms on complex setups.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'medium',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'jquery_ui_scripts' => array(
                'name'        => __('Interactive UI Scripts (jQuery UI)', 'stripboard'),
                'description' => __('Loads jQuery UI components like date pickers, sliders, and drag-and-drop. Many contact form and booking plugins depend on these. Disable only if you have confirmed nothing on your site uses jQuery UI.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'medium',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'masonry_script' => array(
                'name'        => __('Photo Grid Layout Scripts', 'stripboard'),
                'description' => __('Loads Masonry.js and ImagesLoaded.js, used for Pinterest-style waterfall image grids. Safe to disable if your theme or galleries do not use a masonry layout.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'low',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'wp_mediaelement' => array(
                'name'        => __('Audio/Video Player Scripts', 'stripboard'),
                'description' => __('Loads the MediaElement.js player used for audio and video blocks. Disable if you do not embed audio or video in your posts and use a third-party player instead.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'medium',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'wp_accessibility' => array(
                'name'        => __('Accessibility Scripts', 'stripboard'),
                'description' => __('Loads wp-a11y.js which announces dynamic UI changes to screen readers. Disable only if you have confirmed no visitors use assistive technology and no plugins rely on it. Removing it may harm accessibility compliance.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'medium',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'version_strings' => array(
                'name'        => __('WordPress Version Number Exposure', 'stripboard'),
                'description' => __('Controls whether WordPress version metadata stays visible in page source and feeds. Disable this to hide version output and reduce version fingerprinting.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'low',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1,
                'children'    => array('disable_wlwmanifest', 'disable_rsd_link', 'disable_wp_shortlink', 'generator_meta_rss')
            ),
            'disable_wlwmanifest' => array(
                'name'        => __('Windows Live Writer Link', 'stripboard'),
                'description' => __('Removes a legacy link tag added for Windows Live Writer, a blogging app discontinued in 2017. Nobody needs this anymore — safe to disable on all sites.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'low',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'disable_wp_shortlink' => array(
                'name'        => __('WordPress Shortlink Tag', 'stripboard'),
                'description' => __('Removes a short URL tag from your page source and HTTP headers. These shortlinks use the ?p=ID format and are rarely used. Safe to disable on all sites.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'low',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1
            ),
            'disable_rest_api_links' => array(
                'name'        => __('REST API Discovery Tags', 'stripboard'),
                'description' => __('Removes hint tags from your page source that tell clients where your REST API lives. The API still works — this only removes the auto-discovery advertisement. Safe for all sites.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'low',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1
            ),
            'disable_rss_feed_links' => array(
                'name'        => __('RSS Feed Discovery Tags', 'stripboard'),
                'description' => __('Removes the RSS and Atom link tags from your page source that tell feed readers where your feeds are. Your feeds still work — this only removes the auto-discovery hints.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'low',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'remove_query_strings' => array(
                'name'        => __('Cache-Friendly Asset URLs', 'stripboard'),
                'description' => __('Removes the ?ver= version number from CSS and JavaScript file URLs. Some proxy servers and CDNs refuse to cache URLs that contain query strings, so removing them can improve cache hit rates.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'low',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'disable_legacy_css' => array(
                'name'        => __('Unused Legacy Styles', 'stripboard'),
                'description' => __('Stops loading old CSS for the classic Recent Comments widget and the classic gallery shortcode. Safe to disable on any site using a modern theme — these stylesheets are virtually never needed.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'low',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'remove_block_library_css' => array(
                'name'        => __('Block Editor CSS', 'stripboard'),
                'description' => __('Removes three Gutenberg stylesheet files from your public pages. Only disable this if your site uses the Classic Editor and no blocks — it will break block layouts if blocks are in use.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'medium',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'disable_auto_trash_empty' => array(
                'name'        => __('Scheduled Trash Cleanup', 'stripboard'),
                'description' => __('WordPress automatically deletes trashed posts after 30 days via a background task. Disable if you prefer to manage trash manually or if the task is adding unnecessary database load.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'low',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),
            'dashicons_guests' => array(
                'name'        => __('Dashicons for Logged-Out Visitors', 'stripboard'),
                'description' => __('WordPress can load the Dashicons icon font on the frontend even for visitors who are not logged in. Disable to save one frontend stylesheet request on most sites.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'low',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'jquery_core_frontend' => array(
                'name'        => __('jQuery Core on Frontend', 'stripboard'),
                'description' => __('Many modern themes no longer need jQuery on public pages. Disable to prevent loading jQuery core on the frontend. Keep enabled if your theme/plugins depend on jQuery.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'high',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'jquery_migrate_admin' => array(
                'name'        => __('jQuery Migrate in Admin', 'stripboard'),
                'description' => __('Removes jQuery Migrate from wp-admin. Useful for cleaner admin loads, but older admin plugins may rely on it.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'medium',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),
            'global_styles_inline_css' => array(
                'name'        => __('Global Styles Inline CSS', 'stripboard'),
                'description' => __('Disables WordPress global styles CSS output used mainly by block themes and block-based styling. Can reduce frontend head bloat on classic themes.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'medium',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'svg_duotone_filters' => array(
                'name'        => __('SVG Duotone Filters Output', 'stripboard'),
                'description' => __('Stops WordPress from outputting hidden SVG filter markup used by some block image effects. Safe on sites not using duotone effects.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'low',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'adjacent_posts_links' => array(
                'name'        => __('Adjacent Post Links in Head', 'stripboard'),
                'description' => __('Removes prev/next relational link tags from the HTML head. Most modern SEO setups do not require these tags.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'low',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'disable_rsd_link' => array(
                'name'        => __('RSD Link Tag', 'stripboard'),
                'description' => __('Removes the legacy Really Simple Discovery (RSD) link tag from page head output. Rarely needed on modern sites.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'low',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'comment_feeds' => array(
                'name'        => __('Comment Feed Endpoints', 'stripboard'),
                'description' => __('Disables comment-specific feed endpoints (RSS2/Atom comments) while keeping normal post feeds configurable separately.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'low',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'wp_sitemaps' => array(
                'name'        => __('Built-In WordPress Sitemaps', 'stripboard'),
                'description' => __('Disables native WordPress XML sitemaps. Useful if your SEO plugin already generates sitemaps to avoid duplicate endpoints.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'medium',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1
            ),
            'remote_block_patterns' => array(
                'name'        => __('Remote Block Pattern Loading', 'stripboard'),
                'description' => __('Prevents WordPress from fetching block patterns from remote sources in wp-admin, reducing background requests and editor clutter.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'low',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),
            'core_block_patterns' => array(
                'name'        => __('Core Block Patterns', 'stripboard'),
                'description' => __('Disables default WordPress block patterns. Helpful on streamlined sites using custom templates or classic editors.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'medium',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),
            'block_editor_assets_non_editors' => array(
                'name'        => __('Block Editor Assets for Non-Editors', 'stripboard'),
                'description' => __('Prevents loading block editor scripts/styles in wp-admin for users who cannot edit posts, reducing backend payload for support/shop roles.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'low',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),

            // ── Security & Privacy ───────────────────────────────────────────
            'rest_api' => array(
                'name'        => __('Unauthenticated REST API Access', 'stripboard'),
                'description' => __('Blocks guest (not logged-in) REST API requests. Logged-in users and authenticated API clients can still use the REST API. This reduces anonymous endpoint exposure without shutting down the API entirely.', 'stripboard'),
                'category'    => 'security',
                'risk'        => 'high',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1,
                'children'    => array('disable_rest_api_links', 'application_passwords', 'user_enumeration')
            ),
            'xmlrpc' => array(
                'name'        => __('Legacy Remote Publishing (XML-RPC)', 'stripboard'),
                'description' => __('An older protocol used by mobile apps, Jetpack, and some desktop blogging tools to post content remotely. Frequently targeted by brute-force attacks. Safe to disable if you do not use mobile posting apps or Jetpack.', 'stripboard'),
                'category'    => 'security',
                'risk'        => 'medium',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1,
                'children'    => array('xmlrpc_pingback')
            ),
            'user_registration' => array(
                'name'        => __('Public User Registration', 'stripboard'),
                'description' => __('Allows visitors to create an account on your site. Disable to prevent new self-registrations — existing users are unaffected. Recommended for sites where only admins should add new users.', 'stripboard'),
                'category'    => 'security',
                'risk'        => 'medium',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1,
                'children'    => array('registration_password')
            ),
            'user_enumeration' => array(
                'name'        => __('Username Discovery', 'stripboard'),
                'description' => __('Controls public username discovery via ?author=1 URLs and REST user endpoints. Disable this feature to block username discovery and reduce information exposure to scanners.', 'stripboard'),
                'category'    => 'security',
                'risk'        => 'low',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1
            ),

            // ── Admin Interface ──────────────────────────────────────────────
            'dashboard_widgets' => array(
                'name'        => __('Dashboard Widgets', 'stripboard'),
                'description' => __('The default information boxes on the WordPress dashboard (Activity, Quick Draft, WordPress Events, etc.). Safe to disable — hides visual clutter for clients without affecting site functionality.', 'stripboard'),
                'category'    => 'admin_ui',
                'risk'        => 'low',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1,
                'children'    => array('wp_news_dashboard', 'welcome_panel', 'browser_update_nag', 'php_update_nag')
            ),
            'admin_bar' => array(
                'name'        => __('Admin Toolbar', 'stripboard'),
                'description' => __('The black bar shown at the top of the page for logged-in users, with links to the dashboard, post editing, and user profile. Disabling removes it site-wide for all users.', 'stripboard'),
                'category'    => 'admin_ui',
                'risk'        => 'medium',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1,
                'children'    => array('admin_bar_script')
            ),
            'customizer' => array(
                'name'        => __('Theme Customizer', 'stripboard'),
                'description' => __('The live preview panel for adjusting site-wide colours, fonts, and layout. Disable to remove it from the Appearance menu. Not available on block themes anyway — safe to disable if you use a block theme.', 'stripboard'),
                'category'    => 'admin_ui',
                'risk'        => 'medium',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1,
                'children'    => array('custom_header', 'custom_background', 'custom_logo', 'site_icon')
            ),
            'theme_editor' => array(
                'name'        => __('Theme File Editor', 'stripboard'),
                'description' => __('The in-admin code editor for directly modifying theme PHP and CSS files. Disabling this is a security best practice — direct file edits via the browser are risky. Recommended to keep disabled after initial setup.', 'stripboard'),
                'category'    => 'admin_ui',
                'risk'        => 'low',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1,
                'children'    => array('plugin_editor')
            ),
            'plugin_editor' => array(
                'name'        => __('Plugin File Editor', 'stripboard'),
                'description' => __('The in-admin code editor for directly modifying plugin PHP files. Disabling this is a security best practice — a single typo can crash your site. Recommended to keep disabled.', 'stripboard'),
                'category'    => 'admin_ui',
                'risk'        => 'low',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),
            'welcome_panel' => array(
                'name'        => __('Welcome Panel', 'stripboard'),
                'description' => __('The "Welcome to WordPress" box shown on the dashboard to new users. Safe to disable once your team is comfortable with the admin — reduces visual clutter.', 'stripboard'),
                'category'    => 'admin_ui',
                'risk'        => 'low',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),

            // ── Feeds & Connections ──────────────────────────────────────────
            'rss_feeds' => array(
                'name'        => __('RSS / Atom Feeds', 'stripboard'),
                'description' => __('Syndication feeds that let readers subscribe to your content via RSS readers or podcast apps. Disable only if you do not want your content syndicated and no services rely on your feeds.', 'stripboard'),
                'category'    => 'feeds',
                'risk'        => 'medium',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1,
                'children'    => array('rdf_feed', 'disable_rss_feed_links')
            ),
            'rdf_feed' => array(
                'name'        => __('RDF Feed (Legacy Syndication)', 'stripboard'),
                'description' => __('An older feed format used before RSS was standardised. Virtually no modern feed reader uses RDF. Safe to disable on all sites.', 'stripboard'),
                'category'    => 'feeds',
                'risk'        => 'low',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'pingbacks' => array(
                'name'        => __('Cross-Site Link Notifications', 'stripboard'),
                'description' => __('Sends and receives notifications when your posts link to other WordPress sites. Frequently abused for spam. Disable unless you specifically want to participate in the pingback/trackback network.', 'stripboard'),
                'category'    => 'feeds',
                'risk'        => 'low',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1
            ),

            // ── Search & Archives ────────────────────────────────────────────
            'search' => array(
                'name'        => __('Site Search', 'stripboard'),
                'description' => __('The built-in WordPress search that lets visitors find content on your site. Disabling returns a 404 for search requests. Only disable if you use an external search service like Algolia or Elasticsearch.', 'stripboard'),
                'category'    => 'archives',
                'risk'        => 'high',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'archives' => array(
                'name'        => __('Date Archive Pages', 'stripboard'),
                'description' => __('Pages that list all posts from a given year, month, or day (e.g. /2024/03/). Rarely linked to on modern sites. Disabling returns a 404 for these URLs and can help avoid duplicate content issues for SEO.', 'stripboard'),
                'category'    => 'archives',
                'risk'        => 'low',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'attachment_pages' => array(
                'name'        => __('Media Attachment Pages', 'stripboard'),
                'description' => __('Individual pages generated for each uploaded image or file (e.g. /photo-of-something/). These thin pages are often weak for SEO. Disabling returns a 404 for attachment URLs; direct file URLs still work.', 'stripboard'),
                'category'    => 'archives',
                'risk'        => 'low',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'author_archives' => array(
                'name'        => __('Author Archive Pages', 'stripboard'),
                'description' => __('Pages that list all posts by a specific author (e.g. /author/john/). On single-author sites these can duplicate your homepage. Disabling returns a 404 for author archive URLs.', 'stripboard'),
                'category'    => 'archives',
                'risk'        => 'low',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),

            // ── Content & Text Processing ─────────────────────────────────────
            'capital_p_dangit' => array(
                'name'        => __('Auto-correct "WordPress" Spelling', 'stripboard'),
                'description' => __('A tiny filter that runs on every content output to correct "Wordpress" to "WordPress". Purely cosmetic — disable to remove one unnecessary string replacement on every page load.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'low',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1
            ),
            'wptexturize' => array(
                'name'        => __('Smart Punctuation (wptexturize)', 'stripboard'),
                'description' => __('Converts straight quotes to curly quotes, em-dashes, and other typographic symbols. CPU-heavy on long content. Disable if your theme or a plugin handles typography.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'low',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1
            ),
            'convert_smilies' => array(
                'name'        => __('Text Smilies to Images', 'stripboard'),
                'description' => __('Converts text emoticons like :-) to image-based smileys. Legacy feature — most sites use native emoji. Safe to disable.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'low',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1
            ),
            'post_formats' => array(
                'name'        => __('Post Formats', 'stripboard'),
                'description' => __('Adds a meta box to choose a post format (aside, gallery, video, etc.). Unused on most modern themes. Disabling it removes UI clutter without affecting content display.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'low',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),
            'link_manager' => array(
                'name'        => __('Link Manager (Blogroll)', 'stripboard'),
                'description' => __('A deprecated link/blogroll manager from early WordPress. Still present as a hidden feature. Disable to remove this legacy code path.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'low',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),

            // ── Media & Images ────────────────────────────────────────────────
            'responsive_images' => array(
                'name'        => __('Responsive Images (srcset)', 'stripboard'),
                'description' => __('Adds srcset/sizes attributes to images for responsive loading. Useful if your CDN or lazy-load plugin handles responsive images instead. Disabling reduces HTML size.', 'stripboard'),
                'category'    => 'media',
                'risk'        => 'medium',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'webp_uploads' => array(
                'name'        => __('WebP Conversion on Upload', 'stripboard'),
                'description' => __('WordPress automatically generates WebP versions of uploaded images. Disable if using a separate image optimization plugin that handles format conversion.', 'stripboard'),
                'category'    => 'media',
                'risk'        => 'medium',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1
            ),
            'pdf_thumbnails' => array(
                'name'        => __('PDF Thumbnail Generation', 'stripboard'),
                'description' => __('Generates thumbnail previews for uploaded PDF files. Saves server resources if PDF previews are not needed in the media library.', 'stripboard'),
                'category'    => 'media',
                'risk'        => 'low',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),

            // ── Frontend Head & Assets ────────────────────────────────────────
            'canonical_links' => array(
                'name'        => __('Canonical Link Tags', 'stripboard'),
                'description' => __('Adds rel=canonical link tags to page headers for SEO. Duplicate tags can confuse crawlers if an SEO plugin is also active. Disable if your SEO plugin handles canonicity.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'low',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'wp_resource_hints' => array(
                'name'        => __('Resource Hints (dns-prefetch, preconnect)', 'stripboard'),
                'description' => __('Adds dns-prefetch and preconnect hints to page headers. Broad toggle for sites that manage resource hints via CDN or theme.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'low',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1,
                'children'    => array('dns_prefetch')
            ),
            'generator_meta_rss' => array(
                'name'        => __('Generator Tag in RSS Feeds', 'stripboard'),
                'description' => __('Adds a WordPress version generator tag to RSS feeds, separate from the HTML generator. Hiding it reduces version fingerprinting via feeds.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'low',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'interactivity_api' => array(
                'name'        => __('Interactivity API Scripts', 'stripboard'),
                'description' => __('Loads the Interactivity API — new in WordPress 6.5 — on pages using interactive blocks. Safe to disable if your site does not use interactive blocks.', 'stripboard'),
                'category'    => 'speed',
                'risk'        => 'medium',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),

            // ── Security & Access ─────────────────────────────────────────────
            'application_passwords' => array(
                'name'        => __('Application Passwords', 'stripboard'),
                'description' => __('Allows external apps to authenticate with WordPress via generated passwords. Reduces attack surface if your site does not use external integrations.', 'stripboard'),
                'category'    => 'security',
                'risk'        => 'medium',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1
            ),
            'login_language_selector' => array(
                'name'        => __('Login Page Language Selector', 'stripboard'),
                'description' => __('Shows a language dropdown on the login page. Single-language sites do not need this. Safe to disable.', 'stripboard'),
                'category'    => 'security',
                'risk'        => 'low',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'lost_password' => array(
                'name'        => __('Lost Password Flow', 'stripboard'),
                'description' => __('The "Lost your password?" link on the login page. Useful for intranets where admins reset passwords manually. Disabling removes the password reset flow entirely.', 'stripboard'),
                'category'    => 'security',
                'risk'        => 'high',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1
            ),

            // ── Admin & Dashboard ─────────────────────────────────────────────
            'wp_news_dashboard' => array(
                'name'        => __('WordPress Events & News Widget', 'stripboard'),
                'description' => __('Removes the WordPress Events and News dashboard widget, which makes an external request to WordPress.org on every dashboard load.', 'stripboard'),
                'category'    => 'admin_ui',
                'risk'        => 'low',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),
            'admin_email_verification' => array(
                'name'        => __('Admin Email Verification Screen', 'stripboard'),
                'description' => __('Removes the periodic "Is this still the admin email?" interruption. Useful for controlled environments where the admin email is stable.', 'stripboard'),
                'category'    => 'admin_ui',
                'risk'        => 'low',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),
            'command_palette' => array(
                'name'        => __('Command Palette', 'stripboard'),
                'description' => __('The Ctrl+K / Cmd+K command palette in the admin. Reduces JavaScript payload for users who do not use keyboard shortcuts.', 'stripboard'),
                'category'    => 'admin_ui',
                'risk'        => 'low',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),
            'privacy_policy_guide' => array(
                'name'        => __('Privacy Policy Guide', 'stripboard'),
                'description' => __('The suggested privacy policy content guide in Tools. Remove if your site already has a bespoke legal policy.', 'stripboard'),
                'category'    => 'admin_ui',
                'risk'        => 'low',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1,
                'children'    => array('export_erase_personal_data')
            ),
            'health_check' => array(
                'name'        => __('Site Health', 'stripboard'),
                'description' => __('The Site Health tool in Tools menu. Disable to remove the menu item and async health checks on locked-down or staging sites.', 'stripboard'),
                'category'    => 'admin_ui',
                'risk'        => 'low',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),
            'export_erase_personal_data' => array(
                'name'        => __('Export / Erase Personal Data Tools', 'stripboard'),
                'description' => __('The Export Personal Data and Erase Personal Data tools under Tools. Disable for sites not subject to GDPR-style data requests.', 'stripboard'),
                'category'    => 'admin_ui',
                'risk'        => 'low',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),

            // ── Block Editor & Site Editor ────────────────────────────────────
            'block_directory' => array(
                'name'        => __('Block Directory', 'stripboard'),
                'description' => __('Allows installing blocks from WordPress.org inside the block editor. Disable to prevent one-click block installation from the editor.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'medium',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),
            'font_library' => array(
                'name'        => __('Font Library', 'stripboard'),
                'description' => __('The Font Library (WordPress 6.5+) for managing Google Fonts locally. Disable if your theme or CDN handles font loading.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'low',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),
            'design_system' => array(
                'name'        => __('Design System (Block Theme UI)', 'stripboard'),
                'description' => __('THE FULL NUKE — removes the entire block theme Design infrastructure including the top-level Design admin menu, wp_template, wp_template_part, wp_global_styles, and wp_block (reusable blocks) post types, their REST API endpoints, block template loading, and all related theme support. Only safe on classic (non-block) themes, or if you never want block editing anywhere.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'high',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1,
                'children'    => array('site_editor', 'global_styles_inline_css', 'svg_duotone_filters', 'core_block_patterns', 'remote_block_patterns', 'pattern_directory')
            ),

            // ── Granular Comment Controls ─────────────────────────────────────
            'comment_cookies' => array(
                'name'        => __('Comment Author Cookies', 'stripboard'),
                'description' => __('Saves comment author name, email, and URL in a cookie for convenience. Disable for privacy-conscious setups.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'low',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'comment_threading' => array(
                'name'        => __('Threaded/Nested Comment Replies', 'stripboard'),
                'description' => __('Allows threaded replies to comments. Disable for a flat comment structure.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'low',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'comment_url_field' => array(
                'name'        => __('Website Field in Comment Form', 'stripboard'),
                'description' => __('Shows a website/URL input field in the comment form. Disable to reduce spam signals and simplify the comment form.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'low',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),



            // ── Theme / Customiser ───────────────────────────────────────────
            'custom_header' => array(
                'name'        => __('Custom Header', 'stripboard'),
                'description' => __('Adds a custom header image uploader to the theme customizer. Disable if your theme handles headers separately.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'low',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),
            'custom_background' => array(
                'name'        => __('Custom Background', 'stripboard'),
                'description' => __('Adds a custom background color/image uploader to the theme customizer. Disable if your theme controls background styling.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'low',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),
            'custom_logo' => array(
                'name'        => __('Custom Logo Uploader', 'stripboard'),
                'description' => __('Adds a custom logo uploader to the theme customizer. Disable if your theme handles logos separately.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'low',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),
            'site_icon' => array(
                'name'        => __('Site Icon (Favicon Uploader)', 'stripboard'),
                'description' => __('The favicon uploader in the customizer. Disable if you set your favicon via theme or CDN.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'low',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),
            'menus' => array(
                'name'        => __('Navigation Menus', 'stripboard'),
                'description' => __('The navigation menu system (Appearance → Menus). Disable if your theme uses a different navigation approach or menus are hardcoded.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'high',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),
            'widgets' => array(
                'name'        => __('Widgets Subsystem', 'stripboard'),
                'description' => __('The entire widgets system including Appearance → Widgets. Disable if your theme uses block-based widget areas or has no widget support.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'high',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),

            // ── Login & Security (additional) ────────────────────────────────
            'login_logo_link' => array(
                'name'        => __('Login Logo Link to WordPress.org', 'stripboard'),
                'description' => __('Removes the WordPress.org link from the login page logo. Useful for white-label client sites.', 'stripboard'),
                'category'    => 'security',
                'risk'        => 'low',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'registration_password' => array(
                'name'        => __('User-Set Password on Registration', 'stripboard'),
                'description' => __('Shows a password field on the registration form. Disable to force WordPress-generated passwords for tighter control.', 'stripboard'),
                'category'    => 'security',
                'risk'        => 'medium',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1
            ),
            'xmlrpc_pingback' => array(
                'name'        => __('XML-RPC Pingback Methods', 'stripboard'),
                'description' => __('Specifically disables pingback XML-RPC methods while leaving other XML-RPC functionality intact. Reduces DDoS attack surface.', 'stripboard'),
                'category'    => 'security',
                'risk'        => 'low',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1
            ),

            // ── Granular Comment Controls (additional) ───────────────────────
            'comment_avatars' => array(
                'name'        => __('Comment Avatars', 'stripboard'),
                'description' => __('Disables avatars inside comments only, without affecting other get_avatar() calls across the site.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'low',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),
            'comment_html' => array(
                'name'        => __('Allowed HTML in Comments', 'stripboard'),
                'description' => __('Strips all HTML tags from comment submissions. Makes comments text-only for improved security.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'low',
                'scope'       => 'frontend',
                'default'     => true,
                'priority'    => 1
            ),

            // ── Admin Nags & Notifications ────────────────────────────────────
            'browser_update_nag' => array(
                'name'        => __('Browser Update Nag', 'stripboard'),
                'description' => __('Removes the browser update nag from the admin dashboard. Reduces noise in controlled environments.', 'stripboard'),
                'category'    => 'admin_ui',
                'risk'        => 'low',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),
            'php_update_nag' => array(
                'name'        => __('PHP Version Update Nag', 'stripboard'),
                'description' => __('Removes the PHP version update nag from the dashboard. Useful when the host manages PHP separately.', 'stripboard'),
                'category'    => 'admin_ui',
                'risk'        => 'low',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),
            'core_auto_update_email' => array(
                'name'        => __('Core Auto-Update Emails', 'stripboard'),
                'description' => __('Suppresses email notifications when WordPress core updates automatically. Does not disable updates themselves — only the notification emails.', 'stripboard'),
                'category'    => 'admin_ui',
                'risk'        => 'low',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),
            'plugin_auto_update_email' => array(
                'name'        => __('Plugin Auto-Update Emails', 'stripboard'),
                'description' => __('Suppresses email notifications when plugins update automatically. Does not disable plugin updates — only the notification emails.', 'stripboard'),
                'category'    => 'admin_ui',
                'risk'        => 'low',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),
            'theme_auto_update_email' => array(
                'name'        => __('Theme Auto-Update Emails', 'stripboard'),
                'description' => __('Suppresses email notifications when themes update automatically. Does not disable theme updates — only the notification emails.', 'stripboard'),
                'category'    => 'admin_ui',
                'risk'        => 'low',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),

            // ── Media Defaults ───────────────────────────────────────────────
            'default_attachment_display' => array(
                'name'        => __('Default Attachment Link Behaviour', 'stripboard'),
                'description' => __('While off, new media insertions behave as if the default link type is “none” (no link). This is applied at runtime only — it does not lock or repeatedly overwrite the Media setting in the database.', 'stripboard'),
                'category'    => 'media',
                'risk'        => 'low',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),

            // ── Block Editor (additional) ────────────────────────────────────
            'pattern_directory' => array(
                'name'        => __('Pattern Directory (Remote Patterns)', 'stripboard'),
                'description' => __('Prevents loading remote block patterns from WordPress.org inside the editor. Reduces external requests.', 'stripboard'),
                'category'    => 'writing',
                'risk'        => 'low',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),

            // ── WooCommerce (additional) ─────────────────────────────────────
            'wc_order_attribution' => array(
                'name'        => __('WooCommerce Order Attribution', 'stripboard'),
                'description' => __('Tracks how customers found your store (source, campaign, etc.). Disable if you do not use WooCommerce built-in analytics.', 'stripboard'),
                'category'    => 'woocommerce',
                'risk'        => 'low',
                'scope'       => 'both',
                'default'     => true,
                'priority'    => 1
            ),
            'wc_new_product_editor' => array(
                'name'        => __('WooCommerce New Product Editor (Beta)', 'stripboard'),
                'description' => __('Opts out of the new block-based product editor beta. Reverts to the classic product editing screen.', 'stripboard'),
                'category'    => 'woocommerce',
                'risk'        => 'low',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1
            ),
            'wc_analytics' => array(
                'name'        => __('WooCommerce Analytics', 'stripboard'),
                'description' => __('Removes WooCommerce analytics scripts and reports. Reduces admin JS payload if you use a third-party analytics tool.', 'stripboard'),
                'category'    => 'woocommerce',
                'risk'        => 'low',
                'scope'       => 'admin',
                'default'     => true,
                'priority'    => 1,
                'children'    => array('wc_order_attribution', 'wc_usage_tracking')
            ),

        );

        // Add WooCommerce features if WooCommerce is active
        if (class_exists('WooCommerce')) {
            $this->features = array_merge($this->features, array(

                // ── WooCommerce ──────────────────────────────────────────────
                'wc_marketing_hub' => array(
                    'name'        => __('WooCommerce Marketing Hub', 'stripboard'),
                    'description' => __('The Marketing section in the WooCommerce admin menu, containing promotions and campaign tools built by WooCommerce. Remove to unclutter the admin menu for stores that do not use it.', 'stripboard'),
                    'category'    => 'woocommerce',
                    'risk'        => 'low',
                    'scope'       => 'admin',
                    'default'     => true,
                    'priority'    => 1,
                    'children'    => array('wc_marketplace_suggestions', 'wc_admin_notices', 'wc_store_alerts', 'wc_home_screen', 'wc_setup_wizard')
                ),
                'wc_marketplace_suggestions' => array(
                    'name'        => __('WooCommerce Extension Suggestions', 'stripboard'),
                    'description' => __('In-admin recommendations to install paid WooCommerce extensions. Disable to stop these upsell prompts from appearing throughout the WooCommerce admin.', 'stripboard'),
                    'category'    => 'woocommerce',
                    'risk'        => 'low',
                    'scope'       => 'admin',
                    'default'     => true,
                    'priority'    => 1
                ),
                'wc_admin_notices' => array(
                    'name'        => __('WooCommerce Promotional Notices', 'stripboard'),
                    'description' => __('Admin-area banners and notices from WooCommerce promoting features, sales, and surveys. Safe to disable for a cleaner admin experience.', 'stripboard'),
                    'category'    => 'woocommerce',
                    'risk'        => 'low',
                    'scope'       => 'admin',
                    'default'     => true,
                    'priority'    => 1
                ),
                'wc_setup_wizard' => array(
                    'name'        => __('WooCommerce Setup Wizard', 'stripboard'),
                    'description' => __('The step-by-step store setup flow shown after installing WooCommerce. Disable once your store is set up to prevent it from re-appearing.', 'stripboard'),
                    'category'    => 'woocommerce',
                    'risk'        => 'low',
                    'scope'       => 'admin',
                    'default'     => true,
                    'priority'    => 1
                ),
                'wc_home_screen' => array(
                    'name'        => __('WooCommerce Home Screen', 'stripboard'),
                    'description' => __('The WooCommerce analytics overview dashboard shown as the default screen. Disable to remove this screen and reduce admin page load — useful on stores that use a different dashboard plugin.', 'stripboard'),
                    'category'    => 'woocommerce',
                    'risk'        => 'low',
                    'scope'       => 'admin',
                    'default'     => true,
                    'priority'    => 1
                ),
                'wc_store_alerts' => array(
                    'name'        => __('WooCommerce Store Alert Banners', 'stripboard'),
                    'description' => __('Top-of-admin notification banners from WooCommerce about store issues and promotions. Safe to disable once you are comfortable managing your store without them.', 'stripboard'),
                    'category'    => 'woocommerce',
                    'risk'        => 'low',
                    'scope'       => 'admin',
                    'default'     => true,
                    'priority'    => 1
                ),
                'wc_usage_tracking' => array(
                    'name'        => __('WooCommerce Usage Tracking', 'stripboard'),
                    'description' => __('Sends anonymous data about your store setup to WooCommerce / Automattic to help them improve the product. Disable for privacy or to reduce background HTTP requests.', 'stripboard'),
                    'category'    => 'woocommerce',
                    'risk'        => 'low',
                    'scope'       => 'both',
                    'default'     => true,
                    'priority'    => 1
                ),
                'wc_checkout_blocks' => array(
                    'name'        => __('WooCommerce Checkout & Cart Blocks', 'stripboard'),
                    'description' => __('The new block-based Cart and Checkout experience. Disabling reverts to the classic shortcode-based checkout. Only disable if you have confirmed your payment gateway works with the classic checkout.', 'stripboard'),
                    'category'    => 'woocommerce',
                    'risk'        => 'high',
                    'scope'       => 'both',
                    'default'     => true,
                    'priority'    => 1,
                    'children'    => array('wc_block_styles', 'wc_cart_fragments', 'wc_conditional_assets', 'wc_password_strength')
                ),
                'wc_block_styles' => array(
                    'name'        => __('WooCommerce Block Styles', 'stripboard'),
                    'description' => __('CSS loaded for WooCommerce block components like the product grid and filter blocks. Disable only if your theme provides its own WooCommerce block styles.', 'stripboard'),
                    'category'    => 'woocommerce',
                    'risk'        => 'medium',
                    'scope'       => 'frontend',
                    'default'     => true,
                    'priority'    => 1
                ),
                'wc_cart_fragments' => array(
                    'name'        => __('WooCommerce Cart Counter Update', 'stripboard'),
                    'description' => __('Makes an AJAX request on every page load to fetch the live cart item count, so the cart icon stays up to date without a full page reload. Disabling removes this request but the cart count may show stale data until the page refreshes.', 'stripboard'),
                    'category'    => 'woocommerce',
                    'risk'        => 'high',
                    'scope'       => 'frontend',
                    'default'     => true,
                    'priority'    => 1
                ),
                'wc_password_strength' => array(
                    'name'        => __('Password Strength Meter', 'stripboard'),
                    'description' => __('Shows a strength indicator when customers set a password at checkout or account creation. Disable to remove this script if your theme provides its own strength checking or if you want to reduce page weight.', 'stripboard'),
                    'category'    => 'woocommerce',
                    'risk'        => 'medium',
                    'scope'       => 'both',
                    'default'     => true,
                    'priority'    => 1
                ),
                'wc_conditional_assets' => array(
                    'name'        => __('WooCommerce Assets on Non-Store Pages', 'stripboard'),
                    'description' => __('Keeps WooCommerce scripts/styles loaded on non-store pages too. Disable this to unload WooCommerce assets outside shop, product, cart, checkout, and account pages for better performance.', 'stripboard'),
                    'category'    => 'woocommerce',
                    'risk'        => 'medium',
                    'scope'       => 'frontend',
                    'default'     => true,
                    'priority'    => 1
                ),
                'wc_reviews' => array(
                    'name'        => __('Product Reviews & Ratings', 'stripboard'),
                    'description' => __('Allows customers to leave star ratings and written reviews on product pages. Disabling removes the review form and hides existing reviews from product pages.', 'stripboard'),
                    'category'    => 'woocommerce',
                    'risk'        => 'medium',
                    'scope'       => 'both',
                    'default'     => true,
                    'priority'    => 1
                )
            ));
        }

        // Allow other plugins to modify the feature list
        $this->features = apply_filters('stripboard_features', $this->features);
    }
    
    /**
     * Initialize feature controls based on current settings
     */
    private function init_feature_controls() {
        $settings = $this->get_settings();
        
        foreach ($this->features as $feature_key => $feature_data) {
            $is_enabled = isset($settings[$feature_key]) ? $settings[$feature_key] : $feature_data['default'];
            
            if (!$is_enabled) {
                $this->disable_feature($feature_key);
            }
        }
    }
    
    /**
     * Disable a specific feature
     */
    private function disable_feature($feature_key) {
        switch ($feature_key) {
            case 'gutenberg':
                add_action('init', array($this, 'disable_gutenberg'));
                break;
                
            case 'classic_editor':
                add_action('init', array($this, 'disable_classic_editor'));
                break;
                
            case 'block_widgets':
                add_filter('use_widgets_block_editor', '__return_false');
                break;
                
            case 'site_editor':
                remove_theme_support('block-templates');
                remove_theme_support('block-template-parts');
                // Use menu removal instead of the risky wp_is_block_theme filter
                add_action('admin_menu', function() {
                    remove_submenu_page('themes.php', 'site-editor.php');
                    remove_submenu_page('themes.php', 'gutenberg-edit-site');
                }, 999);
                break;
                
            case 'design_system':
                // Remove theme support for all FSE features
                remove_theme_support('block-templates');
                remove_theme_support('block-template-parts');
                remove_theme_support('core-block-patterns');

                // Unregister Design-related post types at high priority
                add_action('init', function() {
                    $post_types_to_remove = array('wp_template', 'wp_template_part', 'wp_global_styles', 'wp_block');
                    foreach ($post_types_to_remove as $pt) {
                        if (post_type_exists($pt)) {
                            unregister_post_type($pt);
                        }
                    }
                }, 100);

                // Remove top-level Design menu + submenu pages
                add_action('admin_menu', function() {
                    remove_menu_page('site-editor.php');
                    remove_menu_page('edit.php?post_type=wp_template');
                    remove_menu_page('edit.php?post_type=wp_template_part');
                    remove_menu_page('edit.php?post_type=wp_global_styles');
                    remove_menu_page('edit.php?post_type=wp_block');
                    remove_submenu_page('themes.php', 'site-editor.php');
                }, 999);

                // Remove REST API endpoints for Design post types
                add_filter('rest_endpoints', function($endpoints) {
                    $remove = array(
                        '/wp/v2/templates',
                        '/wp/v2/template-parts',
                        '/wp/v2/global-styles',
                        '/wp/v2/global-styles/themes/(?P<stylesheet>[\/\w.-]+)',
                        '/wp/v2/themes',
                        '/wp/v2/block-types',
                    );
                    foreach ($remove as $route) {
                        if (isset($endpoints[$route])) {
                            unset($endpoints[$route]);
                        }
                    }
                    return $endpoints;
                });

                // Disable block template resolution on frontend
                remove_filter('template_include', 'wp_resolve_block_template', 5);

                // Disable global styles CSS output
                remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
                remove_action('wp_footer', 'wp_enqueue_global_styles', 1);
                remove_action('wp_body_open', 'wp_global_styles_render_svg_filters');
                remove_action('admin_body_open', 'wp_global_styles_render_svg_filters');

                // Disable remote block patterns / pattern directory
                add_filter('should_load_remote_block_patterns', '__return_false');

                // Deregister block directory script
                add_action('enqueue_block_editor_assets', function() {
                    wp_deregister_script('wp-block-directory');
                }, 100);
                break;
                
            case 'posts':
                add_action('init', function() {
                    unregister_post_type('post');
                }, 99);
                // Remove from admin menu
                add_action('admin_menu', function() {
                    remove_menu_page('edit.php');
                });
                // Remove from admin bar
                add_action('admin_bar_menu', function($wp_admin_bar) {
                    $wp_admin_bar->remove_node('new-post');
                }, 999);
                break;
                
            case 'pages':
                add_action('init', function() {
                    unregister_post_type('page');
                }, 99);
                // Remove from admin menu
                add_action('admin_menu', function() {
                    remove_menu_page('edit.php?post_type=page');
                });
                // Remove from admin bar
                add_action('admin_bar_menu', function($wp_admin_bar) {
                    $wp_admin_bar->remove_node('new-page');
                }, 999);
                break;
                
            case 'attachments':
                add_action('init', function() {
                    unregister_post_type('attachment');
                }, 99);
                // Remove from admin menu
                add_action('admin_menu', function() {
                    remove_menu_page('upload.php');
                });
                // Remove from admin bar
                add_action('admin_bar_menu', function($wp_admin_bar) {
                    $wp_admin_bar->remove_node('new-media');
                }, 999);
                break;
                
            case 'categories':
                add_action('init', function() {
                    unregister_taxonomy('category');
                }, 99);
                break;
                
            case 'tags':
                add_action('init', function() {
                    unregister_taxonomy('post_tag');
                }, 99);
                break;
                
            case 'comments':
                add_action('init', array($this, 'disable_comments'));
                break;
                
            case 'pingbacks':
                add_action('init', array($this, 'disable_pingbacks'));
                break;
                
            case 'rest_api':
                add_filter('rest_authentication_errors', function($result) {
                    // Respect any authentication error added earlier in the chain.
                    if (!empty($result)) {
                        return $result;
                    }

                    // Allow authenticated users (admins/editors/API clients with auth).
                    if (is_user_logged_in()) {
                        return $result;
                    }

                    // Block unauthenticated (guest) REST API access.
                    return new WP_Error('rest_disabled_guests', __('REST API is disabled for guest users.', 'stripboard'), array('status' => 401));
                });
                break;
                
            case 'xmlrpc':
                add_filter('xmlrpc_enabled', '__return_false');
                break;
                
            case 'rss_feeds':
                add_action('do_feed', array($this, 'disable_feeds'), 1);
                add_action('do_feed_rdf', array($this, 'disable_feeds'), 1);
                add_action('do_feed_rss', array($this, 'disable_feeds'), 1);
                add_action('do_feed_rss2', array($this, 'disable_feeds'), 1);
                add_action('do_feed_atom', array($this, 'disable_feeds'), 1);
                break;
                
            case 'rdf_feed':
                add_action('do_feed_rdf', array($this, 'disable_feeds'), 1);
                break;
                
            case 'embeds':
                add_action('init', array($this, 'disable_embeds'));
                break;
                
            case 'emoji':
                add_action('init', array($this, 'disable_emoji'));
                break;
                
            case 'heartbeat':
                add_action('init', array($this, 'disable_heartbeat'));
                break;
                
                
            case 'user_registration':
                add_filter('option_users_can_register', '__return_false');
                break;
                
            case 'author_archives':
                add_action('template_redirect', array($this, 'disable_author_archives'));
                break;
                
            case 'search':
                add_action('parse_query', array($this, 'disable_search'));
                break;
                
            case 'archives':
                add_action('template_redirect', array($this, 'disable_date_archives'));
                break;
                
            case 'attachment_pages':
                add_action('template_redirect', array($this, 'disable_attachment_pages'));
                break;
                
            case 'revisions':
                add_filter('wp_revisions_to_keep', '__return_zero');
                remove_action('pre_post_update', 'wp_save_post_revision');
                break;
                
            case 'autosave':
                add_action('wp_print_scripts', function() {
                    wp_deregister_script('autosave');
                });
                break;
                
            case 'dashboard_widgets':
                add_action('wp_dashboard_setup', array($this, 'remove_dashboard_widgets'));
                break;
                
            case 'admin_bar':
                add_filter('show_admin_bar', '__return_false');
                break;
                
            case 'customizer':
                add_action('admin_menu', function() {
                    remove_submenu_page('themes.php', 'customize.php');
                }, 999);
                break;
                
            case 'theme_editor':
                add_filter('map_meta_cap', function($caps, $cap) {
                    if ('edit_themes' === $cap) {
                        $caps[] = 'do_not_allow';
                    }
                    return $caps;
                }, 10, 2);
                add_action('admin_menu', function() {
                    remove_submenu_page('themes.php', 'theme-editor.php');
                }, 999);
                break;
                
            case 'plugin_editor':
                add_filter('map_meta_cap', function($caps, $cap) {
                    if ('edit_plugins' === $cap) {
                        $caps[] = 'do_not_allow';
                    }
                    return $caps;
                }, 10, 2);
                add_action('admin_menu', function() {
                    remove_submenu_page('plugins.php', 'plugin-editor.php');
                }, 999);
                break;
                
            case 'welcome_panel':
                add_action('wp_dashboard_setup', function() {
                    remove_action('welcome_panel', 'wp_welcome_panel');
                });
                break;
                
            case 'gravatars':
                add_filter('pre_get_avatar', '__return_false');
                add_filter('get_avatar', '__return_false');
                break;
                
                
                
            case 'dns_prefetch':
                remove_action('wp_head', 'wp_resource_hints', 2);
                break;
                
            case 'google_fonts':
                add_action('wp_enqueue_scripts', array($this, 'remove_google_fonts'), 999);
                break;

            case 'dashicons_guests':
                add_action('wp_enqueue_scripts', function() {
                    if (!is_user_logged_in()) {
                        wp_deregister_style('dashicons');
                    }
                }, 100);
                break;

            case 'jquery_core_frontend':
                add_action('wp_enqueue_scripts', function() {
                    wp_deregister_script('jquery');
                    wp_deregister_script('jquery-core');
                }, 100);
                break;

            case 'jquery_migrate_admin':
                add_action('admin_enqueue_scripts', function() {
                    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
                    if (is_object($screen) && isset($screen->id) && 'settings_page_stripboard' === $screen->id) {
                        return;
                    }

                    wp_deregister_script('jquery-migrate');
                }, 100);
                break;
                
            case 'jquery_migrate':
                add_action('wp_enqueue_scripts', function() {
                    wp_deregister_script('jquery-migrate');
                });
                break;
                
            case 'wp_embed_script':
                add_action('wp_enqueue_scripts', function() {
                    wp_deregister_script('wp-embed');
                }, 100);
                // Also remove the embed script from wp_footer
                remove_action('wp_footer', 'wp_oembed_add_discovery_links');
                remove_action('wp_head', 'wp_oembed_add_discovery_links');
                break;
                
            case 'comment_reply_script':
                add_action('wp_enqueue_scripts', function() {
                    wp_deregister_script('comment-reply');
                }, 100);
                break;
                
            case 'admin_bar_script':
                add_action('wp_enqueue_scripts', function() {
                    wp_deregister_script('admin-bar');
                }, 100);
                break;
                
            case 'backbone_underscore':
                add_action('wp_enqueue_scripts', function() {
                    wp_deregister_script('backbone');
                    wp_deregister_script('underscore');
                }, 100);
                break;
                
            case 'wp_util_script':
                add_action('wp_enqueue_scripts', function() {
                    wp_deregister_script('wp-util');
                    wp_deregister_script('wp-a11y');
                    wp_deregister_script('wp-sanitize');
                }, 100);
                break;
                
            case 'jquery_ui_scripts':
                add_action('wp_enqueue_scripts', function() {
                    $jquery_ui_scripts = array(
                        'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-mouse',
                        'jquery-ui-accordion', 'jquery-ui-autocomplete', 'jquery-ui-button',
                        'jquery-ui-datepicker', 'jquery-ui-dialog', 'jquery-ui-draggable',
                        'jquery-ui-droppable', 'jquery-ui-menu', 'jquery-ui-position',
                        'jquery-ui-progressbar', 'jquery-ui-resizable', 'jquery-ui-selectable',
                        'jquery-ui-selectmenu', 'jquery-ui-slider', 'jquery-ui-sortable',
                        'jquery-ui-spinner', 'jquery-ui-tabs', 'jquery-ui-tooltip',
                        'jquery-ui-effects-core', 'jquery-ui-effects-blind', 'jquery-ui-effects-bounce',
                        'jquery-ui-effects-clip', 'jquery-ui-effects-drop', 'jquery-ui-effects-explode',
                        'jquery-ui-effects-fade', 'jquery-ui-effects-fold', 'jquery-ui-effects-highlight',
                        'jquery-ui-effects-puff', 'jquery-ui-effects-pulsate', 'jquery-ui-effects-scale',
                        'jquery-ui-effects-shake', 'jquery-ui-effects-size', 'jquery-ui-effects-slide',
                        'jquery-ui-effects-transfer'
                    );
                    foreach ($jquery_ui_scripts as $script) {
                        wp_deregister_script($script);
                    }
                }, 100);
                break;
                
            case 'masonry_script':
                add_action('wp_enqueue_scripts', function() {
                    wp_deregister_script('masonry');
                    wp_deregister_script('imagesloaded');
                    wp_deregister_script('jquery-masonry');
                }, 100);
                break;
                
            case 'wp_mediaelement':
                add_action('wp_enqueue_scripts', function() {
                    wp_deregister_script('wp-mediaelement');
                    wp_deregister_script('mediaelement');
                    wp_deregister_script('mediaelement-core');
                    wp_deregister_script('mediaelement-migrate');
                    wp_deregister_style('wp-mediaelement');
                    wp_deregister_style('mediaelement');
                }, 100);
                break;
                
            case 'wp_accessibility':
                add_action('wp_enqueue_scripts', function() {
                    wp_deregister_script('wp-a11y');
                }, 100);
                break;
                
            case 'version_strings':
                remove_action('wp_head', 'wp_generator');
                add_filter('the_generator', '__return_empty_string');
                break;
                
            case 'user_enumeration':
                add_action('init', array($this, 'disable_user_enumeration'));
                break;
                
            // WooCommerce features
            case 'wc_marketing_hub':
                add_action('admin_menu', function() {
                    remove_menu_page('wc-admin&path=/marketing');
                }, 999);
                break;
                
            case 'wc_marketplace_suggestions':
                add_filter('woocommerce_allow_marketplace_suggestions', '__return_false');
                break;
                
            case 'wc_admin_notices':
                // Target only WooCommerce-specific promotional notices.
                // Avoids the dangerous remove_all_actions('admin_notices') pattern.
                add_action('admin_head', function() {
                    // Hide WooCommerce promotional banner and survey notices
                    remove_action('admin_notices', 'woocommerce_show_admin_notice_marketplace_suggestions');
                    remove_action('admin_notices', 'woocommerce_show_admin_notice_recommended_extensions');
                    remove_action('admin_notices', 'woocommerce_show_admin_notice_survey');
                }, 1);
                add_filter('woocommerce_allow_marketplace_suggestions', '__return_false');
                break;
                
            case 'wc_setup_wizard':
                add_filter('woocommerce_enable_setup_wizard', '__return_false');
                add_filter('woocommerce_show_admin_notice', '__return_false');
                break;
                
            case 'wc_home_screen':
                add_filter('woocommerce_admin_features', function($features) {
                    return array_values(array_diff((array) $features, array('homescreen')));
                });
                break;
                
            case 'wc_store_alerts':
                add_filter('woocommerce_admin_features', function($features) {
                    return array_values(array_diff((array) $features, array('store-alerts')));
                });
                break;
                
            case 'wc_usage_tracking':
                add_filter('woocommerce_tracker_last_send_time', '__return_zero');
                add_filter('woocommerce_allow_tracking', '__return_false');
                break;
                
            case 'wc_checkout_blocks':
                add_action('wp_enqueue_scripts', function() {
                    wp_dequeue_style('wc-blocks-style');
                    wp_dequeue_script('wc-blocks');
                });
                break;
                
            case 'wc_block_styles':
                add_action('wp_enqueue_scripts', function() {
                    wp_dequeue_style('wc-blocks-style');
                    wp_dequeue_style('wc-blocks-vendors-style');
                });
                break;
                
            case 'wc_cart_fragments':
                add_action('wp_enqueue_scripts', function() {
                    wp_dequeue_script('wc-cart-fragments');
                });
                break;
                
            case 'wc_password_strength':
                add_action('wp_enqueue_scripts', function() {
                    wp_dequeue_script('wc-password-strength-meter');
                });
                break;

            case 'wc_conditional_assets':
                add_action('wp_enqueue_scripts', array($this, 'conditionally_disable_woocommerce_assets'), 99);
                break;
                
            case 'wc_reviews':
                add_filter('woocommerce_product_reviews_enabled', '__return_false');
                break;

            // Performance — Head Bloat
            case 'disable_wlwmanifest':
                remove_action('wp_head', 'wlwmanifest_link');
                break;

            case 'disable_wp_shortlink':
                remove_action('wp_head', 'wp_shortlink_wp_head');
                remove_action('template_redirect', 'wp_shortlink_header', 11);
                break;

            case 'disable_rest_api_links':
                remove_action('wp_head', 'rest_output_link_wp_head');
                remove_action('template_redirect', 'rest_output_link_header', 11);
                break;

            case 'disable_rss_feed_links':
                remove_action('wp_head', 'feed_links', 2);
                remove_action('wp_head', 'feed_links_extra', 3);
                break;

            case 'adjacent_posts_links':
                remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0);
                break;

            case 'disable_rsd_link':
                remove_action('wp_head', 'rsd_link');
                break;

            case 'comment_feeds':
                add_action('do_feed_rss2_comments', array($this, 'disable_feeds'), 1);
                add_action('do_feed_atom_comments', array($this, 'disable_feeds'), 1);
                break;

            case 'wp_sitemaps':
                add_filter('wp_sitemaps_enabled', '__return_false');
                break;

            // Performance — Scripts & Styles
            case 'remove_query_strings':
                add_filter('script_loader_src', array($this, 'remove_query_strings_from_src'), 15, 1);
                add_filter('style_loader_src', array($this, 'remove_query_strings_from_src'), 15, 1);
                break;

            case 'disable_legacy_css':
                add_filter('show_recent_comments_widget_style', '__return_false');
                add_action('wp_enqueue_scripts', function() {
                    wp_dequeue_style('classic-theme-styles');
                    wp_dequeue_style('wp-block-library-theme');
                }, 20);
                break;

            case 'remove_block_library_css':
                add_action('wp_enqueue_scripts', function() {
                    wp_dequeue_style('wp-block-library');
                    wp_dequeue_style('wp-block-library-theme');
                    wp_dequeue_style('global-styles');
                }, 100);
                break;

            case 'global_styles_inline_css':
                remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
                remove_action('wp_footer', 'wp_enqueue_global_styles', 1);
                break;

            case 'svg_duotone_filters':
                remove_action('wp_body_open', 'wp_global_styles_render_svg_filters');
                remove_action('admin_body_open', 'wp_global_styles_render_svg_filters');
                break;

            case 'remote_block_patterns':
                add_filter('should_load_remote_block_patterns', '__return_false');
                break;

            case 'core_block_patterns':
                remove_theme_support('core-block-patterns');
                break;

            case 'block_editor_assets_non_editors':
                add_action('admin_enqueue_scripts', function() {
                    if (!current_user_can('edit_posts')) {
                        wp_dequeue_script('wp-edit-post');
                        wp_dequeue_script('wp-editor');
                        wp_dequeue_style('wp-block-library');
                    }
                }, 100);
                break;

            // Performance — Images
            case 'disable_lazy_load':
                add_filter('wp_lazy_loading_enabled', '__return_false');
                break;

            case 'disable_auto_scaling_images':
                add_filter('big_image_size_threshold', '__return_false');
                break;

            // Performance — Database / Background Tasks
            case 'disable_auto_trash_empty':
                remove_action('wp_scheduled_delete', 'wp_scheduled_delete');
                break;

            // ── Content & Text Processing (Phase 2) ──────────────────────────
            case 'capital_p_dangit':
                add_action('init', function() {
                    remove_filter('the_content', 'capital_P_dangit', 11);
                    remove_filter('the_title', 'capital_P_dangit', 11);
                    remove_filter('comment_text', 'capital_P_dangit', 31);
                    remove_filter('the_excerpt', 'capital_P_dangit', 11);
                });
                break;

            case 'wptexturize':
                add_filter('run_wptexturize', '__return_false');
                break;

            case 'convert_smilies':
                add_action('init', function() {
                    remove_filter('the_content', 'convert_smilies', 20);
                    remove_filter('the_excerpt', 'convert_smilies', 20);
                    remove_filter('comment_text', 'convert_smilies', 20);
                });
                break;

            case 'post_formats':
                $this->run_on_hook_or_now('after_setup_theme', function() {
                    remove_theme_support('post-formats');
                }, 100);
                break;

            case 'link_manager':
                add_filter('pre_option_link_manager_enabled', '__return_false');
                break;

            // ── Media & Images (Phase 2) ─────────────────────────────────────
            case 'responsive_images':
                add_filter('wp_calculate_image_srcset', '__return_false');
                add_filter('wp_calculate_image_sizes', '__return_false');
                break;

            case 'webp_uploads':
                add_filter('wp_upload_image_mime_transforms', '__return_empty_array');
                break;

            case 'pdf_thumbnails':
                add_filter('fallback_intermediate_image_sizes', '__return_empty_array');
                break;

            // ── Frontend Head & Assets (Phase 2) ─────────────────────────────
            case 'canonical_links':
                remove_action('wp_head', 'rel_canonical');
                break;

            case 'wp_resource_hints':
                remove_action('wp_head', 'wp_resource_hints', 2);
                break;

            case 'generator_meta_rss':
                add_filter('the_generator', '__return_empty_string');
                break;

            case 'interactivity_api':
                add_action('wp_enqueue_scripts', function() {
                    wp_deregister_script('wp-interactivity');
                }, 100);
                break;

            // ── Security & Access (Phase 2) ──────────────────────────────────
            case 'application_passwords':
                add_filter('wp_is_application_passwords_available', '__return_false');
                break;

            case 'login_language_selector':
                add_filter('login_display_language_dropdown', '__return_false');
                break;

            case 'lost_password':
                add_action('login_init', function() {
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public login query var.
                    if (isset($_GET['action']) && 'lostpassword' === sanitize_key(wp_unslash($_GET['action']))) {
                        wp_safe_redirect(home_url('/'));
                        exit;
                    }
                });
                break;

            // ── Admin & Dashboard (Phase 2) ──────────────────────────────────
            case 'wp_news_dashboard':
                add_action('wp_dashboard_setup', function() {
                    remove_meta_box('dashboard_primary', 'dashboard', 'side');
                });
                break;

            case 'admin_email_verification':
                add_filter('admin_email_check_interval', '__return_zero');
                break;

            case 'command_palette':
                add_action('admin_enqueue_scripts', function() {
                    wp_deregister_script('wp-commands');
                }, 100);
                break;

            case 'privacy_policy_guide':
                add_action('admin_menu', function() {
                    remove_submenu_page('tools.php', 'privacy.php');
                }, 999);
                break;

            case 'health_check':
                add_action('admin_menu', function() {
                    remove_submenu_page('tools.php', 'site-health.php');
                    remove_submenu_page('tools.php', 'health-check.php');
                }, 999);
                break;

            case 'export_erase_personal_data':
                add_action('admin_menu', function() {
                    remove_submenu_page('tools.php', 'export-personal-data.php');
                    remove_submenu_page('tools.php', 'erase-personal-data.php');
                }, 999);
                break;

            // ── Block Editor (Phase 2) ───────────────────────────────────────
            case 'block_directory':
                add_action('enqueue_block_editor_assets', function() {
                    wp_deregister_script('wp-block-directory');
                }, 100);
                break;

            case 'font_library':
                add_action('init', function() {
                    remove_theme_support('font-library');
                }, 100);
                break;

            // ── Granular Comment Controls (Phase 2) ──────────────────────────
            case 'comment_cookies':
                add_action('init', function() {
                    remove_action('set_comment_cookies', 'wp_set_comment_cookies');
                });
                break;

            case 'comment_threading':
                add_filter('option_thread_comments', '__return_zero');
                break;

            case 'comment_url_field':
                add_filter('comment_form_default_fields', function($fields) {
                    if (isset($fields['url'])) {
                        unset($fields['url']);
                    }
                    return $fields;
                });
                break;

            // ── Theme / Customiser (Phase 2) ────────────────────────────────
            case 'custom_header':
                $this->run_on_hook_or_now('after_setup_theme', function() {
                    remove_theme_support('custom-header');
                }, 100);
                break;

            case 'custom_background':
                $this->run_on_hook_or_now('after_setup_theme', function() {
                    remove_theme_support('custom-background');
                }, 100);
                break;

            case 'custom_logo':
                $this->run_on_hook_or_now('after_setup_theme', function() {
                    remove_theme_support('custom-logo');
                }, 100);
                break;

            case 'site_icon':
                add_action('admin_menu', function() {
                    remove_submenu_page('themes.php', 'customize.php?autofocus[control]=site_icon');
                }, 999);
                break;

            case 'menus':
                $this->run_on_hook_or_now('after_setup_theme', function() {
                    remove_theme_support('menus');
                    remove_theme_support('nav-menus');
                }, 100);
                add_action('admin_menu', function() {
                    remove_menu_page('nav-menus.php');
                }, 999);
                break;

            case 'widgets':
                add_action('widgets_init', function() {
                    global $wp_registered_sidebars;
                    $wp_registered_sidebars = array();
                }, 100);
                add_action('admin_menu', function() {
                    remove_submenu_page('themes.php', 'widgets.php');
                }, 999);
                break;

            // ── Login & Security (Phase 2) ───────────────────────────────────
            case 'login_logo_link':
                add_filter('login_headerurl', '__return_empty_string');
                add_filter('login_headertext', '__return_empty_string');
                break;

            case 'registration_password':
                add_filter('show_password_fields', '__return_false');
                break;

            case 'xmlrpc_pingback':
                add_filter('xmlrpc_methods', function($methods) {
                    unset($methods['pingback.ping']);
                    unset($methods['pingback.extensions.getPingbacks']);
                    return $methods;
                });
                break;

            // ── Granular Comment Controls (Phase 2) ──────────────────────────
            case 'comment_avatars':
                add_filter('get_comment_avatar_url', '__return_false');
                add_filter('get_avatar_comment_types', '__return_empty_array');
                break;

            case 'comment_html':
                add_filter('pre_comment_content', 'wp_filter_nohtml_kses');
                add_filter('comment_allowed_tags', '__return_empty_array');
                break;

            // ── Admin Nags & Notifications (Phase 2) ─────────────────────────
            case 'browser_update_nag':
                add_filter('wp_check_browser_version', '__return_null');
                remove_action('admin_notices', 'check_browser_version');
                break;

            case 'php_update_nag':
                add_action('admin_menu', function() {
                    remove_meta_box('dashboard_php_nag', 'dashboard', 'normal');
                    remove_action('admin_notices', 'update_nag', 3);
                });
                break;

            case 'core_auto_update_email':
                add_filter('auto_core_update_send_email', '__return_false');
                break;

            case 'plugin_auto_update_email':
                add_filter('auto_plugin_update_send_email', '__return_false');
                break;

            case 'theme_auto_update_email':
                add_filter('auto_theme_update_send_email', '__return_false');
                break;

            // ── Media Defaults ───────────────────────────────────────────────
            case 'default_attachment_display':
                // Runtime only: do not update_option() or lock pre_update_option_*.
                // Admins remain free to change Settings → Media; we only override reads
                // while this StripBoard toggle is off.
                add_filter('option_image_default_link_type', function() {
                    return 'none';
                });
                add_filter('default_option_image_default_link_type', function() {
                    return 'none';
                });
                break;

            // ── Block Editor (Phase 2) ───────────────────────────────────────
            case 'pattern_directory':
                add_filter('should_load_remote_block_patterns', '__return_false');
                break;

            // ── WooCommerce (Phase 2) ────────────────────────────────────────
            case 'wc_order_attribution':
                add_filter('woocommerce_order_attribution_enabled', '__return_false');
                break;

            case 'wc_new_product_editor':
                add_filter('woocommerce_feature_new_product_editor_enabled', '__return_false');
                break;

            case 'wc_analytics':
                add_action('admin_enqueue_scripts', function() {
                    wp_dequeue_script('wc-admin-analytics');
                    wp_dequeue_style('wc-admin-analytics');
                }, 100);
                break;
        }

        /**
         * Fires when a feature is being stripped.
         *
         * Custom features registered via `stripboard_features` should hook here:
         * `add_action( 'stripboard_disable_{$feature_key}', 'callback' );`
         *
         * @param string $feature_key Feature slug being disabled.
         */
        do_action("stripboard_disable_{$feature_key}");
    }
    
    /**
     * Get plugin settings
     */
    public function get_settings() {
        return get_option($this->options_key, array());
    }

    /**
     * Copy settings from the previous wp-strip option key when present.
     */
    private function maybe_migrate_legacy_settings() {
        if (false !== get_option($this->options_key, false)) {
            return;
        }

        foreach (array('disable_kit_settings', 'wp_strip_settings') as $legacy_key) {
            $legacy = get_option($legacy_key, false);
            if (false === $legacy || !is_array($legacy)) {
                continue;
            }
            update_option($this->options_key, $legacy);
            delete_option($legacy_key);
            return;
        }
    }
    
    /**
     * Update plugin settings
     */
    public function update_settings($settings) {
        return update_option($this->options_key, $settings);
    }

    /**
     * Public helper: whether a registered feature is currently enabled.
     *
     * @param string $feature_key Feature slug.
     * @return bool|null True/false for registered features; null if unknown.
     */
    public static function is_enabled($feature_key) {
        $instance = self::get_instance();
        return $instance->is_feature_enabled($feature_key);
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Activation can run after init in admin; ensure registry exists for defaults.
        $this->boot_features();

        // Set default settings if none exist
        if (!get_option($this->options_key)) {
            $default_settings = array();
            foreach ($this->features as $key => $feature) {
                $default_settings[$key] = $feature['default'];
            }
            $this->update_settings($default_settings);
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

/**
 * Procedural helper for integrators.
 *
 * @param string $feature_key Feature slug.
 * @return bool|null True/false for registered features; null if plugin inactive or key unknown.
 */
function stripboard_is_feature_enabled($feature_key) {
    if (!class_exists('Stripboard')) {
        return null;
    }
    return Stripboard::is_enabled($feature_key);
}

// Initialize the plugin
Stripboard::get_instance();
