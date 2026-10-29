# Reproducible environment (Phase 1)

This document describes how to build a **content-bearing** Dana-Farber Impact Drupal site from Git alone, without the owner’s laptop database.

## Primary path: DDEV (recommended)

Same as [LOCAL_ENVIRONMENT_SETUP.md](./LOCAL_ENVIRONMENT_SETUP.md):

```bash
cd drupal
ddev start
ddev composer install
```

### Option A — Team SQL dump (fastest)

If you have a `.sql.gz` from the owner:

```bash
ddev import-db --file=/path/to/dump.sql.gz
ddev drush cr
ddev launch
```

### Option B — Bootstrap from config + WordPress JSON (no dump)

Committed artifacts:

| Artifact | Location |
| --- | --- |
| Config export | `drupal/web/sites/default/files/sync/` (~454 items) |
| Migration JSON | `drupal/web/modules/custom/df_migrate/data/*.json` |
| WP refresh pipeline | `drupal/migration-data/fetch_wordpress_model.py` |

WordPress front-end assets for the `df_impact` theme must live **inside** `drupal/web/themes/custom/df_impact/wp-assets/` (committed). Refresh from the reference pack after asset updates:

```bash
./scripts/repro/vendor-wp-assets.sh
./scripts/repro/verify-wp-port-assets.sh   # fails loudly if any wp_port URL ≠ 200
```

One-shot script (runs host Drush against DDEV DB port):

```bash
chmod +x scripts/repro/bootstrap-from-migrations.sh
./scripts/repro/bootstrap-from-migrations.sh
```

After `drush cim`, `config:status` should report no differences. Stale `filter.format.*` `roles:` keys, unused **Config Split**, and YAML ordering drift were removed from the export (Oct 2026).

Manual equivalent:

1. Copy `scripts/repro/settings.local.example.php` → `drupal/web/sites/default/settings.local.php`
2. Set `DDEV_DB_PORT` from `ddev describe` (MariaDB `3306 -> 127.0.0.1:PORT`)
3. `cd drupal && ./vendor/bin/drush -r web site:install standard -y`
4. `./vendor/bin/drush -r web config:set system.site uuid 58f71e08-0da1-46f5-8ea4-6568da8ebf2a -y`
5. `./vendor/bin/drush -r web entity:delete shortcut -y && ./vendor/bin/drush -r web cim -y`
6. Re-enable `df_migrate`; run migrations in order (see `drupal/migration-data/WORDPRESS_MODEL_SYNC.md`)
7. `./vendor/bin/drush -r web df-migrate:sync-wordpress-paths && ./vendor/bin/drush -r web cr`

**Serve for parity harness** (when `ddev launch` returns 500 due to container networking):

```bash
export DDEV_DB_PORT=<port>
cd drupal && ./vendor/bin/drush -r web serve 127.0.0.1:8888
```

Set `DRUPAL_BASE_URL=http://127.0.0.1:8888` for Playwright.

### Refresh content from live WordPress

SiteGround captcha blocks anonymous REST/sitemap from many IPs. On a developer machine with browser cookies:

```bash
cd drupal/migration-data
# export WP_COOKIES_FILE=/path/to/cookies.txt   # recommended
python3 fetch_wordpress_model.py
cd ../..
./scripts/repro/bootstrap-from-migrations.sh   # or drush migrate:import --update …
```

See [WORDPRESS_MODEL_SYNC.md](../drupal/migration-data/WORDPRESS_MODEL_SYNC.md).

## Parity harness

```bash
cd parity
npm ci
npm run manifest          # crawl + merge export JSON
npx playwright install chromium
DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:sample
```

Full manifest run: `npm run parity:full` (long; CI should use sample + nightly full).

Reports: `parity/reports/latest-report.html`, `parity/reports/runs/<id>/results.json`.

## Known local pitfalls

| Symptom | Cause | Mitigation |
| --- | --- | --- |
| DDEV web 500, DB connection timeout | Web container cannot reach `db` on some Linux VMs | Use `drush serve` on host + `settings.local.php` + `DDEV_DB_PORT` |
| `df_media_images` migration fails | Remote download / map issues | Images still under `files/wp-images/`; re-run or use stage_file_proxy |
| Empty manifest crawl | SiteGround 202 / captcha | Merge export JSON (`npm run manifest:exports`) or use cookies |

## What we still need from the owner

- **Preferred:** periodic sanitized **database dump** for fastest onboarding, or
- **cookies.txt** export for `fetch_wordpress_model.py`, or
- Decision on **SearchStax** credentials for search-page parity (optional).
