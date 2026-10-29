#!/usr/bin/env bash
# Reproducible Drupal content bootstrap without a laptop SQL dump.
# Requires: DDEV running (database reachable on published host port).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
DRUPAL="$ROOT/drupal"
bash "$ROOT/scripts/repro/vendor-wp-assets.sh"
SITE_UUID="58f71e08-0da1-46f5-8ea4-6568da8ebf2a"

cd "$DRUPAL"

if ! command -v ddev >/dev/null 2>&1; then
  echo "DDEV is required. See docs/REPRODUCIBLE_ENVIRONMENT.md"
  exit 1
fi

ddev start

DB_PORT="$(ddev describe 2>/dev/null | sed -n 's/.*3306 -> 127.0.0.1:\([0-9]*\).*/\1/p' | head -1)"
if [[ -z "${DB_PORT}" ]]; then
  echo "Could not detect published DB port from ddev describe"
  exit 1
fi

export DDEV_DB_PORT="$DB_PORT"

SETTINGS_LOCAL="$DRUPAL/web/sites/default/settings.local.php"
if [[ ! -f "$SETTINGS_LOCAL" ]]; then
  cp "$ROOT/scripts/repro/settings.local.example.php" "$SETTINGS_LOCAL"
  echo "Created settings.local.php (gitignored)"
fi

DRUSH=(./vendor/bin/drush -r web)

echo "==> Standard install (empty DB)"
"${DRUSH[@]}" site:install standard -y --account-name=admin --account-pass=admin

echo "==> Align site UUID + import config export"
"${DRUSH[@]}" config:set system.site uuid "$SITE_UUID" -y
"${DRUSH[@]}" entity:delete shortcut -y
# site:install seeds system.logging with a different hash; remove before sync import.
"${DRUSH[@]}" config:delete system.logging -y 2>/dev/null || true
"${DRUSH[@]}" cim -y
"${DRUSH[@]}" updatedb -y
"${DRUSH[@]}" pm:uninstall df_migrate -y || true
"${DRUSH[@]}" pm:enable df_migrate -y

echo "==> WordPress uploads path (same URLs as live WP)"
mkdir -p web/sites/default/files/wp-content/uploads web/wp-content
ln -sfn ../sites/default/files/wp-content/uploads web/wp-content/uploads

if [[ -f "$ROOT/drupal/migration-data/private/wp-export.xml" ]]; then
  echo "==> Regenerate migration JSON from WXR export"
  python3 "$ROOT/drupal/migration-data/process_wxr_data.py"
  python3 "$ROOT/drupal/migration-data/parse_wxr_export.py"
fi

echo "==> Migrate WordPress JSON (committed under df_migrate/data)"
for m in df_taxonomy_topics df_taxonomy_cancer_types df_taxonomy_channels df_media_images df_issues df_articles df_in_brief; do
  echo "--- $m"
  "${DRUSH[@]}" migrate:import "$m" -y || true
done

echo "==> Paths, redirects, cache, homepage hero media"
"${DRUSH[@]}" df-migrate:sync-wordpress-paths
if ! "${DRUSH[@]}" php:eval "echo function_exists('_df_setup_backfill_latest_issue_hero_media') ? 'ok' : 'no';" | grep -q ok; then
  "${DRUSH[@]}" php:eval "\Drupal::moduleHandler()->loadInclude('df_setup', 'install');"
fi
"${DRUSH[@]}" php:eval "echo _df_setup_backfill_latest_issue_hero_media();"
"${DRUSH[@]}" cr

echo "==> wp_port theme assets (must be HTTP 200 under DDEV)"
export DRUPAL_BASE_URL="${DRUPAL_BASE_URL:-http://df-impact.ddev.site}"
bash "$ROOT/scripts/repro/verify-wp-port-assets.sh"

echo "Done. Use: ddev launch OR ./vendor/bin/drush -r web serve 127.0.0.1:8888"
echo "Admin: drush uli (with DDEV_DB_PORT set if using host drush)"
