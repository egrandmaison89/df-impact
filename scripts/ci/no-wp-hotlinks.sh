#!/usr/bin/env bash
# Fails when theme or custom module code references Photon (i0.wp.com).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

if grep -R -n --include='*.php' --include='*.module' --include='*.theme' --include='*.inc' --include='*.twig' --include='*.yml' --include='*.css' --include='*.js' -e 'i0.wp.com' \
  drupal/web/themes/custom drupal/web/modules/custom \
  --exclude-dir=wp-assets
then
  echo "ERROR: i0.wp.com reference found in theme or custom module code." >&2
  exit 1
fi

echo "No i0.wp.com references in theme or custom module code."
