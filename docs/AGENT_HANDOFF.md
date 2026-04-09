# Agent handoff — Dana-Farber Impact (Drupal)

Quick context for engineers and AI agents picking up this repository.

## Canonical docs

- **[DRUPAL_REBUILD_PLAN.md](../DRUPAL_REBUILD_PLAN.md)** — Product and technical source of truth (content model, ADRs, editorial guide, appendices).
- **[STANDUP.md](../STANDUP.md)** — Dated engineering snapshot (what shipped, pre-launch checklist, operational notes).
- **[docs/ADMIN_UX_OPTIONS.md](ADMIN_UX_OPTIONS.md)** — Stakeholder-facing admin UX options.

## Local environment

- **DDEV:** Project URL is typically `https://df-impact.ddev.site` (confirm with `ddev describe`).
- **Drush:** Prefer `ddev drush` from the Drupal project root (`drupal/` or docroot per your layout).

## Configuration sync directory

Exports live under **`drupal/web/sites/default/files/sync/`**. Import/export with `drush cim` / `drush cex` after confirming `$settings['config_sync_directory']` in `settings.php` / `settings.local.php`.

## Migration artifacts

- **Python pipeline:** `drupal/migration-data/process_wp_data.py` reads raw WP JSON exports in that directory and writes `articles.json`, `topics.json`, etc., under `drupal/web/modules/custom/df_migrate/data/` (paths relative to Drupal docroot for migrate YMLs).
- **Drupal migrations:** Custom module `df_migrate` (`migrate_plus` YAML in `config/install/`). Enable `df_migrate`, `migrate_plus`, `migrate_tools` as needed; run with `drush migrate:import`.

## Custom modules

| Module | Purpose |
|--------|---------|
| `df_migrate` | WordPress → Drupal JSON migrations |
| `df_site_snippets` | Configurable header/footer HTML for trusted editors |

## Mistakes log

Known pitfalls (moderation + migration, Search API DB, DDEV nginx, etc.) are recorded in **DRUPAL_REBUILD_PLAN.md §5.3** and summarized in **STANDUP.md**.
