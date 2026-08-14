# StripBoard

Simply disable unwanted WordPress features from one settings board.

**Live plugin:** https://wordpress.org/plugins/stripboard/

This repository tracks the **latest** plugin code on `main`. Previous versions are published as [GitHub Releases](https://github.com/gmatta01/stripboard/releases).

## Installation

Install from WordPress.org (**Plugins → Add New → StripBoard**) or download the latest release zip.

## Safety

Kill switch in `wp-config.php`:

```php
define( 'STRIPBOARD_BYPASS', true );
```

## Requirements

- WordPress 5.9+
- PHP 7.4+

## License

GPL v2 or later — see [license.txt](license.txt)
