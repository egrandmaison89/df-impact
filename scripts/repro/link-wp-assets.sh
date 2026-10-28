#!/usr/bin/env bash
# Deprecated: symlinks break under DDEV (only drupal/ is mounted). Use vendor-wp-assets.sh.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
exec bash "$ROOT/scripts/repro/vendor-wp-assets.sh"
