# Dana-Farber Impact — handoff

Drupal 11 rebuild of danafarberimpact.org, prepared for DFCI Enterprise Web Solutions to add to the shared Acquia Cloud Site Factory codebase (the Labs repo). This tree is the site. It is not an install profile yet. Packaging for ACSF is described in [docs/acsf-packaging-proposal.md](docs/acsf-packaging-proposal.md) and is not implemented here.

The site is handed off as this codebase, a database dump, and the public files directory. It is not rebuilt from WordPress.

## Versions

| Item | Value |
| --- | --- |
| Drupal core | 11.4.11 (`drupal/core-recommended:^11.4.11`) |
| PHP | 8.3 or newer. Core requires `>=8.3.0`. Local DDEV uses PHP 8.3. |
| Composer | 2.x |
| Database used in development | MariaDB 11.8 (DDEV). Drupal 11 also runs on MySQL 8. |
| Docroot | `drupal/web` |
| Default theme | `df_impact` (`drupal/web/themes/custom/df_impact`). No base theme. |
| Admin theme | Gin |

## Install PHP dependencies

`drupal/vendor/` is not in git.

```bash
cd drupal
composer install
```

DDEV: `ddev composer install` from `drupal/`. Drupal core and contrib under `drupal/web/` are still committed, so `composer install` must match `composer.lock`. Do not run a broad `composer update` as part of bringing the site up.

## Custom modules

All live under `drupal/web/modules/custom/`.

| Module | Role |
| --- | --- |
| `df_setup` | Front page, archives, legacy paths, article HTML cleanup, and the WordPress reference JSON the theme and other modules read at runtime (`df_setup/data/`). |
| `df_secondary` | Public search rewrite (`/?s=`), RSS, robots, sitemap aliases, legacy redirects, and `/wp-content/uploads/` rewriting. |
| `df_metatag` | Front-end head tags and JSON-LD. Depends on Metatag. |
| `df_editor` | Editor roles, site chrome settings, issue publish workflow, and HTML filters. |
| `df_launch` | Adobe Launch, OneTrust, Meta pixel, and related tags. Injection is off unless `df_launch.settings:enabled` is true and the request is Acquia `prod` (or `$settings['df_launch_tags_enabled']` is set). |
| `df_site_snippets` | Optional header and footer HTML stored in configuration. |

`df_editor` depends on `df_setup`, `df_site_snippets`, and Scheduler. `df_secondary` depends on `df_setup` and `df_metatag`.

## Theme

`df_impact` is the public theme. WordPress-derived CSS and images that the theme needs are vendored in the theme (`wp-assets/`). Gin is the admin theme.

## Configuration

Sync directory, outside the webroot:

`drupal/config/sync`

`drupal/web/sites/default/settings.php` sets:

```php
$settings['config_sync_directory'] = dirname(__DIR__, 3) . '/config/sync';
```

That is `../config/sync` relative to the docroot, as an absolute path so php-fpm and Drush agree.

If `settings.local.php` exists, it is loaded last and wins. The example at `scripts/repro/settings.local.example.php` uses the same path. A local file that still points at `sites/default/files/sync` will import the wrong directory. Update or remove that line before `drush cim`.

```bash
cd drupal
ddev drush cim -y
ddev drush cr
```

Export is `ddev drush cex -y`. Do not export into the public files directory.

## Database and files

1. Import the SQL dump (`ddev import-db --file=/path/to/dump.sql.gz`, or the Labs equivalent).
2. Place public files at `drupal/web/sites/default/files/`. Article and issue media used on the site are under `files/wp-images/`. About 1,700 of those images are also in git (about 93 MB). Still rsync a full files tree from the source environment. Derivatives under `files/styles/`, and aggregated `css/`, `js/`, and `php/`, are generated and are not the source of truth.
3. Run the update steps below if this codebase is newer than the dump.

`drush cim` after the dump aligns configuration with git. Take the dump from a database that already has the module uninstalls in the section below, or run those uninstalls before this code is checked out.

## Site search

Public search is Search API with the database backend, not Solr and not SearchStax.

- Server `df_impact_search`, index `df_impact_content`.
- View `df_impact_search` at `/search-legacy`.
- `df_secondary` rewrites `/?s=cancer` (and `/page/N/?s=`) onto that view.
- The header form is core's search form block.

After a content import, rebuild the index if results are empty:

```bash
ddev drush search-api:clear df_impact_content
ddev drush search-api:index df_impact_content
```

## What was removed

SearchStax was a sandbox for a finished, unrelated project. `df_searchstax`, `searchstax_ui_demo`, the repo-root `search_ui_demo/` sample, and `df_searchstax.settings` are gone. No SearchStax or `search_api_solr` package was required.

The one-time WordPress migration is gone: `df_migrate`, Migrate Plus, Migrate Tools, Migrate Source CSV, and `drupal/migration-data/`. Core Migrate is uninstalled in config. Reference JSON and two small helpers the running site still reads (`TextEntityHelper`, `IssuePageWpFixture`) live in `df_setup`.

Also removed as unused: Facets (enabled, no facet configuration) and Config Split (required, never enabled). `league/csv` left with Migrate Source CSV.

The parity harness (`parity/`) is not in this tree. History, including SearchStax, the migration pipeline, and the harness, is on tag `archive/pre-acsf-handoff-47352229`.

```bash
git checkout archive/pre-acsf-handoff-47352229 -- parity
```

## Known caveats

- Finish `drush updatedb` on the previous codebase before checking this one out. Several `df_setup` updates call migration scripts that are no longer here. Those updates no-op if the script is missing. They must run while the old code is still present.
- `df_secondary` used to depend on `df_migrate`. On the old code, uninstall `df_migrate` only after that dependency line is removed (steps below).
- Uninstalling Migrate does not drop `migrate_map_*` / `migrate_message_*` tables. The theme still consults `migrate_map_df_articles` when that table exists, and falls back to path aliases. Do not drop those tables as part of this cleanup.
- `stage_file_proxy` is enabled in exported config. Turn it off for production. This cleanup does not change that config.
- `df_launch` tags stay off outside Acquia prod. See `docs/deploy/ACQUIA_RUNBOOK.md`.
- `coffee` (admin command palette) is enabled. It is an admin convenience, not part of the public site.
- Core Search and Search API are both enabled. The public results page is the Search API view. The header block is core's search form.
- `google_analytics`, `google_tag`, and `df_launch` can all inject tags. Only `df_launch` is gated to production. Confirm with the Labs team which tag modules belong on the shared codebase before enabling them in prod.
- Older docs (`DRUPAL_REBUILD_PLAN.md`, `docs/PATH_FORWARD.md`, `docs/LOCAL_ENVIRONMENT_SETUP.md`) still mention `files/sync` and the migration pipeline. This file and `drupal/config/sync` are the current locations.
- `drupal/scripts/` still contains one-time site-build scripts from early development, including `import-redirects.php` and `publish-migrated-content.php`. They are not part of runtime. `import-redirects.php` points at a JSON file that was removed with the migration.

## Local update on a Mac that already has the site

Do the uninstalls on the old code. Then check out this branch. `composer install` cannot uninstall a module whose code is already gone, and Drupal will not uninstall `df_migrate` while `df_secondary` still depends on it.

From the repo root, with Docker running and the old branch checked out:

```bash
cd drupal
ddev drush updatedb -y

# Let Drupal uninstall df_migrate. This line is already gone on the cleanup branch.
sed -i.bak '/df_migrate:df_migrate/d' web/modules/custom/df_secondary/df_secondary.info.yml
ddev drush cr

ddev drush pm:uninstall df_searchstax -y
ddev drush pm:uninstall df_migrate -y
ddev drush pm:uninstall migrate_tools -y
ddev drush pm:uninstall migrate_plus -y
ddev drush pm:uninstall migrate -y
ddev drush pm:uninstall facets -y
ddev drush cr

cd ..
git checkout -- drupal/web/modules/custom/df_secondary/df_secondary.info.yml
git checkout cursor/acsf-handoff-cleanup-5a3f

cd drupal
composer install
```

If `web/sites/default/settings.local.php` sets `config_sync_directory`, point it at `dirname(__DIR__, 3) . '/config/sync'` before importing config.

```bash
ddev drush updatedb -y
ddev drush cim -y
ddev drush cr
```

`searchstax_ui_demo` and `config_split` were not installed. `migrate_source_csv` was not installed. Skip them.

Without DDEV, use `./vendor/bin/drush -r web` from `drupal/` after `composer install`, and run that same uninstall sequence against the old code first.

## What ACSF integration needs

Labs will add this site to an existing shared codebase. Expected shape, not built in this branch:

- An install profile for the modules, theme, and default configuration.
- A site directory (settings, services, and the files pointer) rather than a second copy of Drupal core.
- `sites.php` (or the factory equivalent) so the Impact hostname maps to that directory.
- Config sync for this site only, outside the webroot, imported on deploy.

Open questions for the Labs team:

- Which Drupal core and PHP versions does the shared codebase run, relative to 11.4.11 and PHP 8.3?
- Which of the contrib projects in `drupal/composer.json` are already in Labs, and at which major versions? The versions here should be aligned rather than copied as a second vendor tree.
- How does Labs sync configuration for one site in a multisite codebase (per-site sync directory, config split, or something else)? Config Split is not installed here.
- Should `stage_file_proxy`, `coffee`, Gin, Editoria11y, Google Analytics, and Google Tag be site-specific or shared?
- What is the profile machine name Labs wants?

Details and tradeoffs: [docs/acsf-packaging-proposal.md](docs/acsf-packaging-proposal.md).
