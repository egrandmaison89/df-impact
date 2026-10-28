#!/usr/bin/env bash
# Copy reference-pack assets into the theme (DDEV-safe; no symlinks outside drupal/).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SRC="$ROOT/parity/reference-pack/assets"
DEST="$ROOT/drupal/web/themes/custom/df_impact/wp-assets"

if [[ ! -d "$SRC" ]]; then
  echo "Missing pack assets: $SRC"
  exit 1
fi

if [[ -L "$DEST" ]] || [[ -e "$DEST" ]]; then
  rm -rf "$DEST"
fi
mkdir -p "$DEST"
if command -v rsync >/dev/null 2>&1; then
  rsync -a "$SRC/" "$DEST/"
else
  cp -a "$SRC/." "$DEST/"
fi
LOGO_THEME="$ROOT/drupal/web/themes/custom/df_impact/images/ImpactDFJF_HeaderLogo_878.png"
if [[ -f "$LOGO_THEME" ]]; then
  cp -f "$LOGO_THEME" "$DEST/ImpactDFJF_HeaderLogo_878.png"
fi

echo "Synced $SRC -> $DEST ($(find "$DEST" -type f | wc -l) files)"
