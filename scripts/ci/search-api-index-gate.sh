#!/usr/bin/env bash
# Search API + cold-cache HTTP gate. Fails if Drupal cannot be installed or indexed.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
DRUPAL="$ROOT/drupal"
bash "$ROOT/scripts/ci/no-wp-hotlinks.sh"
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
if ! curl -s -o /dev/null --max-time 2 "${BASE_URL}/"; then
  php -S 127.0.0.1:8888 -t "$DRUPAL/web" "$DRUPAL/web/.ht.router.php" >/tmp/df-drush-serve.log 2>&1 &
  echo $! > /tmp/df-drush-serve.pid
  ready=0
  for _ in $(seq 1 30); do
    if curl -s -o /dev/null --max-time 2 "${BASE_URL}/"; then
      ready=1
      break
    fi
    sleep 1
  done
  if [[ "$ready" != "1" ]]; then
    echo "---- drush serve log ----" >&2
    cat /tmp/df-drush-serve.log >&2 || true
    fail "Drupal did not accept HTTP connections at ${BASE_URL}"
  fi
fi

http_code() {
  local path="$1"
  local code
  code=$(curl -s -L -o /tmp/df-http-body -w "%{http_code}" --max-time 30 "${BASE_URL}${path}" || echo "000")
  if [[ "$code" != "200" ]]; then
    echo "GET ${path} -> ${code}" >&2
    head -c 800 /tmp/df-http-body >&2 || true
    echo >&2
    echo "---- drush serve log ----" >&2
    cat /tmp/df-drush-serve.log >&2 || true
    "${DRUSH[@]}" watchdog:show --count=8 >&2 || true
    fail "GET ${path} returned ${code}"
  fi
  echo "200 ${path}"
}

http_code "/"
for path in "/search?s=" "/search?s=cancer" "/browse-issues" "/in-brief" "/late-fall-2022-issue" "/user/login" "/tag/digital-exclusives" "/tag/digital-exclusives/" "/rss.xml"; do
  http_code "$path"
done

assert_no_wp_hosts() {
  local path="$1"
  curl -s -L -o /tmp/df-http-body --max-time 30 "${BASE_URL}${path}" || fail "Could not fetch ${path} for host check"
  python3 - "$path" <<'PY' || fail "Rendered page requests a WordPress host"
import re, sys
path = sys.argv[1]
html = open("/tmp/df-http-body", "r", errors="replace").read()
pattern = re.compile(r"(?:https?:)?//(?:[a-z0-9.-]+\.)?(?:danafarberimpact\.org|wp\.com)\b", re.I)
hits = pattern.findall(html)
if hits:
    print(path, "off-domain WordPress URLs:", len(hits), file=sys.stderr)
    for hit in hits[:8]:
        print(" ", hit, file=sys.stderr)
    sys.exit(1)
print("no wordpress hosts", path)
PY
}

for path in "/" "/search?s=cancer" "/browse-issues" "/in-brief" "/late-fall-2022-issue" "/rss.xml"; do
  assert_no_wp_hosts "$path"
done

curl -s -L -o /tmp/df-search-body --max-time 30 "${BASE_URL}/search?s=cancer" || fail "Could not fetch search"
python3 - <<'PY' || fail "Search canonical is not /search?s=cancer"
import re
html = open("/tmp/df-search-body", "r", errors="replace").read()
match = re.search(r'rel="canonical" href="([^"]+)"', html)
if not match:
    raise SystemExit("missing canonical")
href = match.group(1)
if not href.endswith("/search?s=cancer") and "search?s=cancer" not in href:
    raise SystemExit(href)
print("canonical", href)
PY

php "$ROOT/scripts/ci/wp-image-stubs.php" || fail "HTML files are committed as images"

if [[ ! -d /tmp/df-blocked-host/node_modules/puppeteer-core ]]; then
  npm install --prefix /tmp/df-blocked-host --no-save --silent puppeteer-core
fi

find_chrome() {
  local candidate
  for candidate in \
    "${CHROME_PATH:-}" \
    /usr/local/bin/google-chrome \
    /usr/bin/google-chrome \
    /usr/bin/google-chrome-stable \
    /usr/bin/chromium \
    /usr/bin/chromium-browser
  do
    if [[ -n "$candidate" && -x "$candidate" ]]; then
      echo "$candidate"
      return 0
    fi
  done
  return 1
}

if ! CHROME="$(find_chrome)"; then
  if [[ ! -d /tmp/df-blocked-host/node_modules/@puppeteer/browsers ]]; then
    npm install --prefix /tmp/df-blocked-host --no-save --silent @puppeteer/browsers
  fi
  install_log=$(cd /tmp/df-blocked-host && npx --no-install @puppeteer/browsers install chrome-headless-shell --path /tmp/df-chrome)
  echo "$install_log"
  CHROME=$(printf '%s\n' "$install_log" | awk '{print $NF}' | tail -1)
  if [[ ! -x "$CHROME" ]]; then
    CHROME=$(find /tmp/df-chrome -type f -name chrome-headless-shell -perm -111 | head -1)
  fi
fi
[[ -n "${CHROME:-}" && -x "$CHROME" ]] || fail "No Chrome or Chromium binary for the blocked-host image check"
PUPPETEER_CORE_ROOT=/tmp/df-blocked-host CHROME_PATH="$CHROME" BASE_URL="$BASE_URL" node "$ROOT/scripts/ci/blocked-host-images.mjs" || fail "Blocked-host image check failed"

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
