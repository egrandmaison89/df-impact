#!/usr/bin/env bash
# Copy reference-pack WordPress assets into the theme (DDEV-safe; no symlinks outside drupal/).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
THEME="$ROOT/drupal/web/themes/custom/df_impact"
PACK="$ROOT/parity/reference-pack/assets"
DEST="$THEME/wp-assets"

if [[ ! -d "$PACK" ]]; then
  echo "Missing reference pack assets: $PACK" >&2
  exit 1
fi

if [[ -L "$DEST" ]]; then
  rm -f "$DEST"
fi
mkdir -p "$DEST"
rsync -a --delete "$PACK/" "$DEST/"

LOGO_SRC="$THEME/images/ImpactDFJF_HeaderLogo_878.png"
LOGO_DEST="$DEST/ImpactDFJF_HeaderLogo_878.png"
if [[ -f "$LOGO_SRC" ]]; then
  cp -f "$LOGO_SRC" "$LOGO_DEST"
elif [[ ! -f "$LOGO_DEST" ]]; then
  echo "Missing header logo: $LOGO_SRC or $LOGO_DEST" >&2
  exit 1
fi

node "$ROOT/parity/scripts/build-wp-derived-assets.mjs"

echo "Vendored wp-assets into $DEST ($(du -sh "$DEST" | awk '{print $1}'))"
