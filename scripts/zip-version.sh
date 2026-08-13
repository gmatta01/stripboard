#!/usr/bin/env bash
# Build a WordPress.org-ready zip from a version folder.
# Usage: ./scripts/zip-version.sh 1.0.4
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="${1:-}"
if [[ -z "$VERSION" || ! -d "$ROOT/$VERSION" ]]; then
  echo "Usage: $0 <version-folder>   e.g. $0 1.0.4" >&2
  echo "Available:" >&2
  find "$ROOT" -maxdepth 1 -type d -name '[0-9]*' -exec basename {} \; | sort >&2
  exit 1
fi

if [[ ! -f "$ROOT/$VERSION/stripboard.php" ]]; then
  echo "Missing $VERSION/stripboard.php" >&2
  exit 1
fi

OUT_DIR="$ROOT/dist"
mkdir -p "$OUT_DIR"
OUT_ZIP="$OUT_DIR/stripboard-${VERSION}.zip"
STAGE=$(mktemp -d)
trap 'rm -rf "$STAGE"' EXIT

mkdir -p "$STAGE/stripboard"
# Copy version contents; never include junk / VCS / OS / ignore files
rsync -a \
  --exclude '.git/' \
  --exclude '.gitignore' \
  --exclude '.DS_Store' \
  --exclude 'Thumbs.db' \
  --exclude '.pi-subagents/' \
  --exclude '*.swp' \
  --exclude '*.swo' \
  --exclude '.env' \
  --exclude '.env.local' \
  "$ROOT/$VERSION/" "$STAGE/stripboard/"

# Belt-and-suspenders: delete any that slipped through
find "$STAGE" -name '.DS_Store' -delete
find "$STAGE" -name '.gitignore' -delete
find "$STAGE" -name 'Thumbs.db' -delete

rm -f "$OUT_ZIP"
(
  cd "$STAGE"
  zip -r "$OUT_ZIP" stripboard \
    -x '*/.DS_Store' \
    -x '*/.gitignore' \
    -x '*/Thumbs.db' \
    -x '*/.git/*'
)

echo "Created: $OUT_ZIP"
unzip -l "$OUT_ZIP" | head -40
