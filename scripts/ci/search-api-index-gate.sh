#!/usr/bin/env bash
# Search API + cold-cache HTTP gate. Fails if Drupal cannot be installed or indexed.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
DRUPAL="$ROOT/drupal"
DB_NAME="${DF_DB_NAME:-dfimpact}"
DB_USER="${DF_DB_USER:-dfimpact}"
DB_PASS="${DF_DB_PASS:-dfimpact}"
DB_HOST="${DF_DB_HOST:-127.0.0.1}"
SITE_UUID="58f71e08-0da1-46f5-8ea4-6568da8ebf2a"
BASE_URL="${DRUPAL_BASE_URL:-http://127.0.0.1:8888}"

fail() {
  echo "ERROR: $*" >&2
  exit 1
}

cd "$DRUPAL"
command -v composer >/dev/null || fail "composer is required"
composer install --no-interaction --prefer-dist

command -v mysqladmin >/dev/null || fail "mysqladmin is required"
mysqladmin ping -h "$DB_HOST" --silent || fail "MySQL is not reachable at ${DB_HOST}"

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
    --db-url="mysql://${DB_USER}:${DB_PASS}@${DB_HOST}/${DB_NAME}" \
    || fail "site:install failed"
fi
"${DRUSH[@]}" status --fields=bootstrap | grep -q Successful || fail "Drupal did not bootstrap"

FIXTURE="${DF_FIXTURE_SQL:-}"
if [[ -z "$FIXTURE" ]]; then
  for candidate in "$ROOT"/drupal/fixtures/*.sql "$ROOT"/drupal/fixtures/*.sql.gz "$ROOT"/fixtures/*.sql.gz; do
    if [[ -f "$candidate" ]]; then
      FIXTURE="$candidate"
      break
    fi
  done
fi
if [[ -n "$FIXTURE" && -f "$FIXTURE" ]]; then
  echo "==> Import fixture ${FIXTURE}"
  if [[ "$FIXTURE" == *.gz ]]; then
    gzip -dc "$FIXTURE" | "${DRUSH[@]}" sql:cli
  else
    "${DRUSH[@]}" sql:cli < "$FIXTURE"
  fi
fi

"${DRUSH[@]}" config:set system.site uuid "$SITE_UUID" -y
SYNC="${DRUPAL}/config/sync"
mkdir -p /tmp/cim-node-types
cp "${SYNC}"/node.type.*.yml /tmp/cim-node-types/
"${DRUSH[@]}" updatedb -y
"${DRUSH[@]}" cim -y --partial --source=/tmp/cim-node-types
"${DRUSH[@]}" cim -y
# Cold caches: this used to 500 when publication_sort had no property definition.
"${DRUSH[@]}" cr
"${DRUSH[@]}" cr

echo "==> search-api:clear + search-api:index df_impact_content"
"${DRUSH[@]}" search-api:clear df_impact_content
"${DRUSH[@]}" search-api:index df_impact_content

# HTTP checks on a real install. Fail if the server cannot be reached.
if ! curl -sf --max-time 2 "${BASE_URL}/" >/dev/null; then
  "${DRUSH[@]}" serve 127.0.0.1:8888 >/tmp/df-drush-serve.log 2>&1 &
  echo $! > /tmp/df-drush-serve.pid
  for _ in 1 2 3 4 5 6 7 8 9 10; do
    curl -sf --max-time 2 "${BASE_URL}/" >/dev/null && break
    sleep 1
  done
fi
curl -sf --max-time 10 -o /dev/null -w "%{http_code}" "${BASE_URL}/" | grep -qx 200 || fail "GET / did not return 200"
for path in "/search?s=" "/search?s=cancer" "/browse-issues" "/in-brief" "/user/login"; do
  code=$(curl -s -o /dev/null -w "%{http_code}" --max-time 20 "${BASE_URL}${path}")
  [[ "$code" == "200" ]] || fail "GET ${path} returned ${code}"
done

# Saved article preview must not 500 after a cold cache rebuild.
"${DRUSH[@]}" php:eval '
$node = \Drupal\node\Entity\Node::create(["type" => "article", "title" => "CI preview article", "status" => 0, "moderation_state" => "draft"]);
$node->save();
echo $node->id();
' > /tmp/df-preview-nid.txt
NID=$(tr -dc "0-9" < /tmp/df-preview-nid.txt)
[[ -n "$NID" ]] || fail "Could not create a preview article"
LOGIN=$("${DRUSH[@]}" user:login --uid=1 --uri="$BASE_URL")
curl -sf -c /tmp/df-ci-cookies -L --max-time 20 "$LOGIN" -o /dev/null || fail "Admin login failed"
CODE=$(curl -s -o /dev/null -w "%{http_code}" -b /tmp/df-ci-cookies --max-time 20 "${BASE_URL}/node/${NID}/edit")
[[ "$CODE" == "200" ]] || fail "GET /node/${NID}/edit returned ${CODE}"
"${DRUSH[@]}" php:eval "
\$node = \Drupal\node\Entity\Node::load(${NID});
\$node->in_preview = TRUE;
\$build = \Drupal::entityTypeManager()->getViewBuilder('node')->view(\$node, 'full');
\Drupal::service('renderer')->renderRoot(\$build);
echo 'preview-render-ok';
" | grep -q preview-render-ok || fail "Saved article preview render failed"
echo "Search API index gate passed."
