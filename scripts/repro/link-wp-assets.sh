#!/usr/bin/env bash
# Symlink reference-pack assets into the theme (required after clone).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
THEME="$ROOT/drupal/web/themes/custom/df_impact"
PACK="$ROOT/parity/reference-pack/assets"
ln -sfn "$PACK" "$THEME/wp-assets"
echo "Linked $THEME/wp-assets -> $PACK"
