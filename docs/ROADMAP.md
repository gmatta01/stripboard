# StripBoard — Post-1.0 Roadmap

Items intentionally deferred past the WordPress.org 1.0 submission. Track here so they do not block packaging.

## 1.1 — Operator tooling

- **WP-CLI** — `wp strip list|enable|disable|export|import`
- **JSON presets** — import/export toggle sets (e.g. “brochure site”, “headless”)
- **Audit log** — record who changed which feature and when (option or custom table)
- **Visual hierarchy** — nest child features under parents in the admin UI (cascade already works)

## 1.2 — Smarter dependencies

- Feature metadata: `requires`, `conflicts`, `implies`
- Third-party compatibility map (e.g. warn when stripping REST while Gutenberg is on for guests-only scenarios)
- Role-scoped toggles (optional; keep site-wide as default)

## 1.3 — Architecture & CI

- Optional per-feature / per-group controller classes (replace giant `disable_feature()` switch gradually)
- PHPCS (WordPress-Extra) + PHPCompatibility in CI
- PHPUnit for settings sanitize, hierarchy cascade, and selected disable helpers
- Keep `doc/features.json` generation in CI so README/catalog never drift

## Out of scope for now

- Multisite-specific network toggles
- Press This / post-by-email era features
- Paid / freemium upsells
