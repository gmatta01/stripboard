# StripBoard

Simply disable unwanted WordPress features from one settings board.

## Source layout

```
stripboard/                 ← git repo (source of truth)
├── 1.0.4/                  ← current plugin code (edit here)
├── 1.0.3/                  ← archived submitted release
├── wordpress-org/          ← directory banners / icons / screenshots
├── docs/                   ← project docs
├── doc/                    ← feature catalog data
├── examples/               ← developer samples
├── scripts/zip-version.sh  ← build a clean zip for a version
└── dist/                   ← generated zips (gitignored)
```

**Active version:** `1.0.4/`

When you ship a new release: copy/bump into a new folder (e.g. `1.0.5/`), update that folder’s version strings, then zip it.

## Build a zip

```bash
./scripts/zip-version.sh 1.0.4
# → dist/stripboard-1.0.4.zip
```

The zip excludes `.DS_Store`, `.gitignore`, `.git`, and other junk. Contents are packed as `stripboard/…` for WordPress installs.

## Installation (from zip)

1. Upload the `stripboard` folder to `/wp-content/plugins/`
2. Activate the plugin
3. Open **Settings → StripBoard**

## Safety

Kill switch in `wp-config.php`:

```php
define( 'STRIPBOARD_BYPASS', true );
```

## Docs

- [`docs/SUBMITTING.md`](docs/SUBMITTING.md) — WordPress.org packaging notes
- [`docs/ROADMAP.md`](docs/ROADMAP.md)
- [`examples/extend-plugin.php`](examples/extend-plugin.php)
- [`doc/features.json`](doc/features.json)

## License

GPL v2 or later — see [`1.0.4/license.txt`](1.0.4/license.txt)
