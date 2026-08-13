# Submitting StripBoard to WordPress.org

## Layout

- Plugin code: version folders (`1.0.4/`, etc.)
- Directory assets: `wordpress-org/` → SVN `assets/` after approval

## Build a review / release zip

```bash
./scripts/zip-version.sh 1.0.4
```

Output: `dist/stripboard-1.0.4.zip`

Excluded automatically: `.git`, `.gitignore`, `.DS_Store`, `Thumbs.db`, env/editor junk.

Included: only files inside the version folder (PHP/JS/CSS, `readme.txt`, `license.txt`, languages, etc.).

## After approval (SVN)

```bash
svn co https://plugins.svn.wordpress.org/stripboard stripboard-svn
# Copy 1.0.4/* → trunk/
# Copy wordpress-org/* → assets/
svn add trunk/* assets/*
svn ci -m "Release 1.0.4"
svn cp trunk tags/1.0.4
svn ci -m "Tag 1.0.4"
```

Readme validator: https://wordpress.org/plugins/developers/readme-validator/
