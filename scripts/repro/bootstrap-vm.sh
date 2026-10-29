#!/usr/bin/env bash
# VM bootstrap without DDEV: PHP 8.3 + MariaDB + config import + migrations.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
DRUPAL="$ROOT/drupal"
SITE_UUID="58f71e08-0da1-46f5-8ea4-6568da8ebf2a"
DB_NAME="${DF_DB_NAME:-df_impact}"
DB_USER="${DF_DB_USER:-df_impact}"
DB_PASS="${DF_DB_PASS:-df_impact}"
DB_HOST="${DF_DB_HOST:-127.0.0.1}"

cd "$DRUPAL"

if ! command -v php >/dev/null 2>&1; then
  echo "PHP is required (php8.3-cli)."
  exit 1
fi

sudo service mariadb start 2>/dev/null || true

sudo mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
sudo mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
sudo mysql -e "GRANT ALL ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost'; FLUSH PRIVILEGES;"

SETTINGS_LOCAL="$DRUPAL/web/sites/default/settings.local.php"
cat > "$SETTINGS_LOCAL" <<PHP
<?php

\$databases['default']['default'] = [
  'database' => '${DB_NAME}',
  'username' => '${DB_USER}',
  'password' => '${DB_PASS}',
  'host' => '${DB_HOST}',
  'port' => '3306',
  'driver' => 'mysql',
  'prefix' => '',
  'collation' => 'utf8mb4_general_ci',
];

\$settings['hash_salt'] = getenv('DRUPAL_HASH_SALT') ?: 'vm-local-dev-only-change-me-58f71e08';
\$settings['config_sync_directory'] = 'sites/default/files/sync';
\$settings['trusted_host_patterns'] = ['.*'];

\$config['system.performance']['css']['preprocess'] = FALSE;
\$config['system.performance']['js']['preprocess'] = FALSE;
PHP

if ! grep -q settings.local.php "$DRUPAL/web/sites/default/settings.php" 2>/dev/null; then
  echo "Enable settings.local.php include in settings.php manually if missing."
fi

bash "$ROOT/scripts/repro/vendor-wp-assets.sh"

DRUSH=(./vendor/bin/drush -r web)

if ! "${DRUSH[@]}" status --fields=bootstrap 2>/dev/null | grep -q Successful; then
  echo "==> Fresh site:install"
  "${DRUSH[@]}" site:install standard -y --account-name=admin --account-pass=admin \
    --db-url="mysql://${DB_USER}:${DB_PASS}@${DB_HOST}/${DB_NAME}"
fi

echo "==> Config import"
"${DRUSH[@]}" config:set system.site uuid "$SITE_UUID" -y
"${DRUSH[@]}" entity:delete shortcut -y 2>/dev/null || true
"${DRUSH[@]}" config:delete system.logging -y 2>/dev/null || true
# site:install seeds Basic page content that blocks a full config sync.
"${DRUSH[@]}" sql:query "DELETE FROM node_field_data; DELETE FROM node_field_revision; DELETE FROM node; DELETE FROM node_revision;" 2>/dev/null || true
"${DRUSH[@]}" cim -y
"${DRUSH[@]}" updatedb -y
"${DRUSH[@]}" pm:enable df_editor -y 2>/dev/null || true
"${DRUSH[@]}" pm:uninstall df_migrate -y || true
"${DRUSH[@]}" pm:enable df_migrate -y

mkdir -p web/sites/default/files/wp-content/uploads web/wp-content
ln -sfn ../sites/default/files/wp-content/uploads web/wp-content/uploads

echo "==> Migrations"
for m in df_taxonomy_topics df_taxonomy_cancer_types df_taxonomy_channels df_media_images df_issues df_articles df_in_brief; do
  echo "--- $m"
  "${DRUSH[@]}" migrate:import "$m" -y || true
done

"${DRUSH[@]}" df-migrate:sync-wordpress-paths
"${DRUSH[@]}" php:eval "\Drupal::moduleHandler()->loadInclude('df_setup', 'install'); echo _df_setup_backfill_latest_issue_hero_media();"
"${DRUSH[@]}" cr

echo "Done. Start server: cd drupal && ./vendor/bin/drush -r web serve 127.0.0.1:8888"
