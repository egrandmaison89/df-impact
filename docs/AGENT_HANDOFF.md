# Agent handoff — Dana-Farber Impact (Drupal)

Quick context for engineers and AI agents picking up this repository.

## Canonical docs

- **[docs/PATH_FORWARD.md](PATH_FORWARD.md)** — Gap analysis vs WordPress, phased roadmap, and implementation log.
- **[DRUPAL_REBUILD_PLAN.md](../DRUPAL_REBUILD_PLAN.md)** — Product and technical source of truth (content model, ADRs, editorial guide, appendices).
- **`standups/STANDUP.md`** — Dated engineering snapshot (what shipped, pre-launch checklist, operational notes). The `standups/` folder is **gitignored**; keep a local copy or sync out of band.
- **[docs/ADMIN_UX_OPTIONS.md](ADMIN_UX_OPTIONS.md)** — Stakeholder-facing admin UX options; includes **frontend local tasks** (public-site editor strip).

## Local environment

DDEV config lives under **`drupal/.ddev/`** (not the repo root). **Docker Desktop** (or another Docker engine) must be running before any `ddev` command.

### After clone (typical flow)

```bash
cd drupal
ddev start
ddev composer install   # if vendor/ is missing or composer.lock changed
ddev drush status       # expect “Drupal bootstrap: Successful” if DB already has a site
```

- **Site URL:** `https://df-impact.ddev.site` (project name `df-impact` in `.ddev/config.yaml`).
- **Open in browser:** `ddev launch` (from `drupal/`).
- **One-time login link:** `ddev drush uli` (paste URL in browser).

### Config import (existing database)

If you have a populated database and exported config in `web/sites/default/files/sync/`:

```bash
ddev drush cim -y
ddev drush cr
```

### Fresh database

If `drush status` shows no installation, either import a SQL dump (`ddev import-db --file=…`) or run a minimal site install, then import config. A full editorial site is not reproducible from Git alone without a DB dump or re-running migrations.

### Homepage, paths, and redirects (after migration)

1. **`df_setup`** (enabled in config): on install it creates a **Home** basic page at `/home` and sets `system.site.page.front` to `/home`. Import config with `drush cim` after pulling.
2. **Bulk path + placement + redirects** from JSON/maps:

   ```bash
   cd drupal
   ddev drush php:script web/modules/custom/df_migrate/scripts/df_sync_wordpress_paths.php
   ```

3. **Inline image / lazy-load repair** on existing nodes (optional re-run):

   ```bash
   ddev drush php:script web/modules/custom/df_migrate/scripts/fix_inline_body_images.php
   ```

The front template shows a **default hero image** when no block is placed in the Hero region; editors can override by placing a block there.

### QA screenshots

Repo-root **`testing/`** is **gitignored**. Use it for local screenshots and scratch artifacts from UI or migration QA (not for files that must ship with the project).

### Git and `sites/default/files`

Do **not** commit Drupal’s generated public files: **`files/css/`**, **`files/js/`** (aggregated assets), **`files/php/`** (compiled Twig), or **`files/styles/`** (image derivatives). They change whenever caches are rebuilt and will spam hundreds of meaningless diffs. This repo’s root **`.gitignore`** excludes those paths. **Do** keep versioned whatever you intend to share (for example **`files/sync/*.yml`** and migrated **`files/wp-images/`** if that is team policy).

### Troubleshooting

- `failed to connect to the docker API` / `docker.sock`: start **Docker Desktop** and wait until it is “running,” then retry `ddev start`.
- **Drush:** Always run via `ddev drush` from **`drupal/`** so paths and PHP match the containers.

## Configuration sync directory

Exports live under **`drupal/web/sites/default/files/sync/`**. Import/export with `drush cim` / `drush cex` after confirming `$settings['config_sync_directory']` in `settings.php` / `settings.local.php`.

## Migration artifacts

- **Python pipeline:** `drupal/migration-data/process_wp_data.py` reads raw WP JSON exports in that directory and writes `articles.json`, `topics.json`, etc., under `drupal/web/modules/custom/df_migrate/data/` (paths relative to Drupal docroot for migrate YMLs).
- **Drupal migrations:** Custom module `df_migrate` (`migrate_plus` YAML in `config/install/`). Enable `df_migrate`, `migrate_plus`, `migrate_tools` as needed; run with `drush migrate:import`.

## Custom modules

| Module | Purpose |
|--------|---------|
| `df_migrate` | WordPress → Drupal JSON migrations |
| `df_setup` | Creates `/home` landing page and sets site front path on install |
| `df_site_snippets` | Configurable header/footer HTML for trusted editors |

## Mistakes log

Known pitfalls (moderation + migration, Search API DB, DDEV nginx, etc.) are recorded in **DRUPAL_REBUILD_PLAN.md §5.3** and summarized in **`standups/STANDUP.md`** (local).
