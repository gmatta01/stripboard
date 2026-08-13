=== StripBoard ===
Contributors: gangesh
Tags: performance, security, disable features, cleanup, woocommerce
Requires at least: 5.9
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Simply disable unwanted WordPress features from one settings board.

== Description ==

StripBoard lets you simply disable unwanted features from WordPress (and WooCommerce) at the load level—using hooks, not menu hiding—all from one settings board.

* Toggleable features across writing, media, speed, security, admin UI, feeds, archives, and WooCommerce
* Plain-English descriptions, risk labels (high / medium / low), and scope tags (admin / frontend / both)
* Parent → child hierarchy with cascade toggles
* Kill switch via `STRIPBOARD_BYPASS` in wp-config.php
* Extensible for developers via documented filters and actions

No bloat. No page builders. No subscriptions.

= Privacy =

StripBoard stores settings in the WordPress options table (`stripboard_settings`) only. It does not send data to remote servers, create accounts, or collect personal information.

= Developer hooks =

* `stripboard_features` — modify the feature registry
* `stripboard_categories` — modify category tabs
* `stripboard_disable_{$feature}` — implement stripping for custom features
* `stripboard_feature_toggled` — react when a setting changes
* `stripboard_validate_setting` — filter values before save
* `stripboard_is_feature_enabled( $key )` — public helper

See the GitHub README for extension examples.

== Installation ==

1. Upload the `stripboard` folder to the `/wp-content/plugins/` directory, or install from Plugins → Add New.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to Settings → StripBoard to configure features.

== Frequently Asked Questions ==

= I disabled something and lost admin access. How do I recover? =

Add this line to `wp-config.php` above the “That's all, stop editing!” comment:

`define( 'STRIPBOARD_BYPASS', true );`

This bypasses all StripBoard logic until you can re-enable features.

= Does disabling REST API break the block editor? =

The REST toggle blocks **unauthenticated** (guest) REST requests only. Logged-in users and authenticated clients can still use the API.

= Do the auto-update email toggles stop WordPress from updating? =

No. Those toggles only suppress notification emails. They do not disable core, plugin, or theme updates.

== Screenshots ==

1. Settings → StripBoard with feature toggles, risk labels, category tabs, and safety guidance.

== Changelog ==

= 1.0.3 =
* Load translated feature strings on init to satisfy WordPress 6.7+ translation timing

= 1.0.2 =
* Soften default attachment link toggle: runtime filter only, no global option lock

= 1.0.1 =
* Rebrand to StripBoard for WordPress.org naming guidelines
* Remove features that interfered with Core update checks or defined DISABLE_WP_CRON
* Contributors list matches plugin owner account

= 1.0.0 =
* Initial public release
* WordPress / WooCommerce feature toggles
* Parent–child hierarchy, kill switch, security warnings
* Developer extension API

== Upgrade Notice ==

= 1.0.3 =
Fixes early translation loading notice on WordPress 6.7+.

= 1.0.2 =
Attachment link behaviour no longer locks the Media setting.

= 1.0.1 =
Review response: new name/slug, safer update/cron handling.
