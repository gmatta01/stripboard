# Submitting StripBoard to WordPress.org

## Source of truth

Plugin code lives in version folders under the git repo:

`/Users/gm/Downloads/Project/stripboard/<version>/`

Directory assets live in:

`wordpress-org/` (banners, icons, screenshots) → copy into SVN `assets/` after approval.

## Build a review / release zip

```bash
cd /Users/gm/Downloads/Project/stripboard
./scripts/zip-version.sh 1.0.4
```

Output: `dist/stripboard-1.0.4.zip`

Excluded automatically: `.git`, `.gitignore`, `.DS_Store`, `Thumbs.db`, env/editor junk.

Included: only files inside the version folder (PHP/JS/CSS, `readme.txt`, `license.txt`, languages, etc.).

Optional: copy the zip into ops history:

`../Disable Kit/submissions/stripboard-1.0.4.zip`

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

Ops mirror of the SVN package: `../Disable Kit/svn-ready/`

Readme validator: https://wordpress.org/plugins/developers/readme-validator/
