# StripBoard

Simply disable unwanted WordPress features from one settings board.

**StripBoard** lets you turn off unused core WordPress (and WooCommerce) features at the load level—hooks, not menu hiding. Every toggle includes plain-English guidance, a risk label, and a scope tag.

No bloat. No page builders. No subscriptions.

## Features

- Toggleable features across writing, media, speed, security, admin UI, feeds, archives, and WooCommerce
- Plain-English descriptions, risk labels (**high** / **medium** / **low**), and scope tags (**admin** / **frontend** / **both**)
- Parent → child hierarchy with cascade toggles
- Kill switch via `STRIPBOARD_BYPASS` in `wp-config.php`
- Extensible for developers via filters and actions

## Requirements

- WordPress 5.9+
- PHP 7.4+
- `manage_options` capability

## Installation

1. Upload the `stripboard` folder to `/wp-content/plugins/`, or install from **Plugins → Add New**
2. Activate the plugin
3. Open **Settings → StripBoard** to configure features

## Safety

### Kill switch

If a toggle locks you out of admin, add this to `wp-config.php` above the “That's all, stop editing!” line:

```php
define( 'STRIPBOARD_BYPASS', true );
```

This bypasses all StripBoard logic until you can re-enable features.

### Other safeguards

- Confirmation dialogs on critical disables
- Risk labels and scope tags on every feature
- Parent → child cascade with locked children when a parent is off

## Privacy

StripBoard stores settings in the WordPress options table (`stripboard_settings`) only. It does not send data to remote servers, create accounts, or collect personal information.

## Developer API

See [`examples/extend-plugin.php`](examples/extend-plugin.php) for copy-paste patterns.

### Helpers

```php
stripboard_is_feature_enabled( 'comments' ); // true|false|null
Stripboard::is_enabled( 'rest_api' );        // true|false|null
```

### Filters

| Hook | Purpose |
|------|---------|
| `stripboard_features` | Add/modify the feature registry |
| `stripboard_categories` | Add/modify admin category tabs |
| `stripboard_validate_setting` | Filter a value before save |

### Actions

| Hook | Purpose |
|------|---------|
| `stripboard_disable_{$feature_key}` | Run when a feature is being disabled |
| `stripboard_feature_toggled` | After save when a value changes |

### Settings storage

Option: `stripboard_settings`. Legacy `disable_kit_settings` / `wp_strip_settings` migrate automatically.

## FAQ

**I disabled something and lost admin access. How do I recover?**  
Use the kill switch above (`STRIPBOARD_BYPASS`).

**Does disabling REST API break the block editor?**  
No. The REST toggle blocks unauthenticated (guest) REST requests only. Logged-in users and authenticated clients can still use the API.

**Do the auto-update email toggles stop WordPress from updating?**  
No. Those toggles only suppress notification emails. They do not disable core, plugin, or theme updates.

## Changelog

### 1.0.4

- Minimal admin UI: neutral gray page background, white panels, square edges; blue only for highlights

### 1.0.3

- Load translated feature strings on init for WordPress 6.7+ translation timing

### 1.0.2

- Soften default attachment link toggle: runtime filter only, no global option lock

### 1.0.1

- Rebrand to StripBoard (`stripboard`)
- Removed update-check interference and `DISABLE_WP_CRON` define toggle
- Contributors: gangesh

### 1.0.0

- Initial public release

## License

GPL v2 or later — see [`1.0.4/license.txt`](1.0.4/license.txt)
