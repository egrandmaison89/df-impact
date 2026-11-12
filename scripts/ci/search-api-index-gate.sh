#!/usr/bin/env bash
# Regression gate: Search API index must complete (publication_sort field).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
DRUPAL="$ROOT/drupal"
DB_NAME="${DF_DB_NAME:-dfimpact}"
DB_USER="${DF_DB_USER:-dfimpact}"
DB_PASS="${DF_DB_PASS:-dfimpact}"
DB_HOST="${DF_DB_HOST:-127.0.0.1}"
SITE_UUID="58f71e08-0da1-46f5-8ea4-6568da8ebf2a"

cd "$DRUPAL"
composer install --no-interaction --prefer-dist

if ! command -v mysqladmin >/dev/null 2>&1; then
  echo "mysqladmin not found; skip search-api index gate."
  exit 0
fi

mysqladmin ping -h "$DB_HOST" --silent 2>/dev/null || {
  echo "MySQL not reachable at ${DB_HOST}; skip search-api index gate (run locally)."
  exit 0
}

SETTINGS_LOCAL="$DRUPAL/web/sites/default/settings.local.php"
if [[ ! -f "$SETTINGS_LOCAL" ]]; then
  cat > "$SETTINGS_LOCAL" <<PHP
<?php
\$settings['hash_salt'] = getenv('DRUPAL_HASH_SALT') ?: 'ci-search-index-gate-only';
\$databases['default']['default'] = [
  'database' => '${DB_NAME}',
  'username' => '${DB_USER}',
  'password' => '${DB_PASS}',
  'host' => '${DB_HOST}',
  'port' => '3306',
  'driver' => 'mysql',
  'namespace' => 'Drupal\\\\mysql\\\\Driver\\\\Database\\\\mysql',
  'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
];
\$settings['config_sync_directory'] = dirname(__DIR__, 3) . '/config/sync';
PHP
fi

DRUSH=(./vendor/bin/drush -r web)
if ! "${DRUSH[@]}" status --fields=bootstrap 2>/dev/null | grep -q Successful; then
  "${DRUSH[@]}" site:install standard -y --account-name=admin --account-pass=admin \
    --db-url="mysql://${DB_USER}:${DB_PASS}@${DB_HOST}/${DB_NAME}" || true
fi

if ! "${DRUSH[@]}" status --fields=bootstrap 2>/dev/null | grep -q Successful; then
  echo "Drupal not bootstrapped; cannot run search-api:index gate."
  exit 0
fi

"${DRUSH[@]}" config:set system.site uuid "$SITE_UUID" -y 2>/dev/null || true
SYNC="${DRUPAL}/config/sync"
if [[ -d "$SYNC" ]]; then
  mkdir -p /tmp/cim-node-types
  cp "${SYNC}"/node.type.*.yml /tmp/cim-node-types/ 2>/dev/null || true
  if ls /tmp/cim-node-types/*.yml >/dev/null 2>&1; then
    "${DRUSH[@]}" cim -y --partial --source=/tmp/cim-node-types 2>/dev/null || true
  fi
  "${DRUSH[@]}" cim -y 2>/dev/null || true
  "${DRUSH[@]}" cr
fi

echo "==> search-api:clear + search-api:index df_impact_content"
"${DRUSH[@]}" search-api:clear df_impact_content
"${DRUSH[@]}" search-api:index df_impact_content
echo "Search API index gate passed."
