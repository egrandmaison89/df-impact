#!/usr/bin/env bash
# Fail if any wp_port library asset is missing or not HTTP 200 on the local Drupal site.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
LIB="$ROOT/drupal/web/themes/custom/df_impact/df_impact.libraries.yml"
THEME="$ROOT/drupal/web/themes/custom/df_impact"
BASE_URL="${DRUPAL_BASE_URL:-http://df-impact.ddev.site}"

if [[ ! -f "$LIB" ]]; then
  echo "Missing libraries.yml: $LIB" >&2
  exit 1
fi

missing=0
count=0
while IFS= read -r rel; do
  [[ -z "$rel" ]] && continue
  count=$((count + 1))
  file="$THEME/$rel"
  if [[ ! -f "$file" ]]; then
    echo "MISSING file: $rel" >&2
    missing=$((missing + 1))
    continue
  fi
  url="$BASE_URL/themes/custom/df_impact/$rel"
  code=$(curl -s -o /dev/null -w '%{http_code}' "$url" || echo "000")
  if [[ "$code" != "200" ]]; then
    echo "HTTP $code for $url" >&2
    missing=$((missing + 1))
  fi
done < <(rg -o 'wp-assets/[^ :]+' "$LIB" | sort -u)

if [[ "$missing" -gt 0 ]]; then
  echo "wp_port asset check failed ($missing problem(s)). BASE_URL=$BASE_URL" >&2
  exit 1
fi

echo "All $count wp_port assets OK at $BASE_URL"
