#!/usr/bin/env bash
# Batch WordPress JSON migrations until unprocessed=0 (VM / drush serve).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT/drupal/web"
DRUSH="$ROOT/drupal/vendor/bin/drush"

run_one() {
  local id=$1
  local limit=${2:-25}
  for i in $(seq 1 500); do
    echo "==== $id batch $i $(date -Iseconds) ===="
    "$DRUSH" migrate:import "$id" --limit="$limit" -y || "$DRUSH" migrate:reset-status "$id"
    local u
    u=$("$DRUSH" migrate:status "$id" --fields=Unprocessed --format=csv 2>/dev/null | tail -1 | tr -d '\r')
    echo "unprocessed=$u"
    if [[ "$u" == "0" ]]; then
      break
    fi
    sleep 1
  done
  "$DRUSH" migrate:status "$id"
}

run_one df_articles 25
run_one df_in_brief 25
run_one df_media_images 50
"$DRUSH" df-migrate:sync-wordpress-paths
"$DRUSH" cr
echo "MIGRATIONS_DONE $(date -Iseconds)"
"$DRUSH" sql:query "SELECT type, COUNT(*) c FROM node_field_data GROUP BY type"
"$DRUSH" migrate:status
