# Development / local layout

Repo layout for maintainers (not the public plugin readme).

```
stripboard/
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

See also [SUBMITTING.md](SUBMITTING.md).
