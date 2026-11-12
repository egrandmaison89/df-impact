# Dana-Farber Impact: operations handoff

Prepared for **Jonathan W. Webb** and DFCI Enterprise Web Solutions (EWS). This document describes the Drupal site as it exists in this repository for placement in the shared Acquia Cloud Site Factory (ACSF) Labs codebase.

The site is the rebuild of **danafarberimpact.org**. Handoff includes **this git tree**, a **database dump**, and the **public files** directory (`sites/default/files/`, especially `wp-images/`). Runtime does **not** depend on WordPress or the removed one-time migration modules.

## What this site is

- A Drupal **11.4.x** magazine site: issues, articles, In Brief items, topics, archives, and WordPress-shaped URLs and templates.
- **Search** is internal: Search API on the database backend, not Solr or SearchStax.
- **Parity** with the former WordPress front end is enforced in the custom theme and modules; the old `parity/` harness is archived on git tag `archive/pre-acsf-handoff-47352229`.

## Stack

| Component | Location / notes |
| --- | --- |
| Composer project | `drupal/` (`composer.json`, `composer.lock`) |
| Docroot | `drupal/web` |
| PHP | 8.3+ (DDEV uses 8.3; align with Labs standard) |
| DB | MariaDB 11.8 locally; MySQL 8 on Acquia is typical |
| Config sync | `drupal/config/sync` via `$settings['config_sync_directory']` in `settings.php` |
| Public theme | `df_impact` (`drupal/web/themes/custom/df_impact`) |
| Admin theme | Gin |

**Key contrib modules** (non-exhaustive; see `drupal/composer.json`): Admin Toolbar, Coffee, Content Moderation, Editoria11y, Field Group, Focal Point, Linkit, Metatag, Paragraphs, Pathauto, Redirect, Scheduler, Search API + Search API DB, Simple XML Sitemap, Stage File Proxy (toggle off for production), Twig Tweak.

**Custom modules** (`drupal/web/modules/custom/`):

| Module | Purpose |
| --- | --- |
| `df_setup` | Home page, archives, legacy paths, reference JSON under `data/`, subscribers for WordPress-shaped routes |
| `df_secondary` | Public search rewrite (`/?s=`), RSS, robots, sitemap aliases, legacy redirects, share links |
| `df_metatag` | Front-end head tags and JSON-LD |
| `df_editor` | Web editor workflow, issue dashboard, Editor HTML filters, site chrome settings |
| `df_launch` | Adobe Launch, OneTrust, Meta pixel, and related tags (gated to production) |
| `df_site_snippets` | Optional header/footer HTML snippets in config |

## Repository layout

```
/
  README.md             Project overview (this handoff is in docs/)
  docs/                 Editor guides, deploy runbook, ACSF proposal
  drupal/
    config/sync/        Exported configuration (import with drush cim)
    web/                Drupal docroot
    .ddev/              Local DDEV project (name: df-impact)
  scripts/              Repro and legacy one-off scripts (not runtime)
```

`drupal/vendor/` is **not** in git. Run `composer install` in `drupal/` on every environment.

## Local setup (DDEV)

1. Install [DDEV](https://ddev.com/) and Docker.
2. `cd drupal && ddev start`
3. `ddev composer install`
4. Import the handoff SQL dump: `ddev import-db --file=/path/to/dump.sql.gz`
5. Sync `drupal/web/sites/default/files/` from the handoff (especially `wp-images/`)
6. Ensure `web/sites/default/settings.local.php` does **not** override `config_sync_directory` to `files/sync`. Use the same path as `settings.php` (`../config/sync` relative to the docroot).

## Deploy steps (any environment)

From `drupal/` with a working Drush and database:

```bash
composer install --no-dev   # production
drush updatedb -y
drush cim -y
drush cr
drush search-api:clear df_impact_content
drush search-api:index df_impact_content
drush simple-sitemap:generate   # after content is current
```

After `cim`, `config:status` may show only **`system.site`** different if `FixHomeFrontPageConfigSubscriber` rewrites `page.front` to the Home node ID. That is expected.

Acquia-specific checklist: [docs/deploy/ACQUIA_RUNBOOK.md](deploy/ACQUIA_RUNBOOK.md).

## Continuous integration

This handoff tree does **not** include the `parity/` pixel and URL harness (it was removed for ACSF packaging). GitHub Actions therefore runs **Drupal smoke (cold cache)** as the required pull request check, defined in `.github/workflows/drupal-smoke-cold-cache.yml` and implemented by `scripts/ci/search-api-index-gate.sh`.

On each PR to `cursor/phase4-chrome-behavior-0b67`, CI:

1. Boots a fresh Drupal site on MySQL 8.
2. Imports exported configuration from `drupal/config/sync` (`drush updatedb`, `drush cim`).
3. Rebuilds caches twice (`drush cr`) so Views and theme registry are cold (this catches Search API field definition errors such as missing `publication_sort` processor properties).
4. Clears and rebuilds the Search API index `df_impact_content`.
5. Serves the docroot and requires HTTP 200 on core public routes (`/`, search, archives, login).
6. Renders a saved article with `in_preview` to ensure node preview does not WSOD.

To reproduce locally (needs MariaDB or MySQL and PHP 8.3):

```bash
bash scripts/ci/search-api-index-gate.sh
```

Optional: set `DF_FIXTURE_SQL` to a database dump path before running the script so CI indexes the full content set instead of a minimal install.

The retired **Parity smoke 200** workflow is not used. To run historical parity checks, restore `parity/` from tag `archive/pre-acsf-handoff-47352229` and use the scripts under `parity/` manually (see older docs under `docs/`).

## Roles and editorial workflow

Workflow **`editorial`** (`workflows.workflow.editorial`) applies to **article**, **in_brief**, **issue**, and **page**.

| State | Meaning |
| --- | --- |
| Draft | Default; unpublished |
| In Review | Submitted for review |
| Published | Live default revision |
| Archived | Unpublished archive state |

| Transition | From | To |
| --- | --- | --- |
| Submit for Review | Draft | In Review |
| Send Back to Draft | In Review | Draft |
| Publish | Draft, Published | Published |
| Create New Draft | Draft, Published | Draft |
| Archive | Published | Archived |

*Note:* Extending **Publish** so it also starts from **In Review**, and related permission updates, may land in a separate open pull request. Confirm the exported config on the branch you deploy.

### `web_editor`

Creates and edits **articles**, **in_brief**, and **issues**. Can use transitions **create_new_draft**, **publish**, and **submit_for_review** (see current `user.role.web_editor.yml`). Can use the **Issue dashboard**, **Editor HTML** format, and Scheduler **Publish on**. Cannot access full site structure admin (blocks, snippets admin UI, user accounts, and similar). See [docs/editors/WEB_EDITOR_GUIDE.md](editors/WEB_EDITOR_GUIDE.md).

### `site_admin`

Full content and configuration for Impact: menus, snippets, homepage hero, taxonomy, URL aliases, and all editorial transitions including **archive** (unpublish path). See [docs/editors/ADMIN_GUIDE.md](editors/ADMIN_GUIDE.md).

### `issue_manager`

Legacy role with broad issue and article edit rights. Prefer **`web_editor`** or **`site_admin`** for new accounts unless Labs policy says otherwise.

## Search

- **Index:** `df_impact_content` on server `df_impact_search` (Search API DB).
- **Results view:** `df_impact_search` at `/search-legacy`.
- **Public URL:** `df_secondary` rewrites `/?s=term` (and paginated variants) to that view. A **blank** `/?s=` lists recent **articles and issues** (no keyword). An **unmatched** term shows the Search Results heading with an empty main region (per live WordPress behavior).
- **Sort:** Blank search orders by indexed **`publication_sort`**, derived from WordPress export post dates in `articles.json` when available, else node changed/created. Reindex after deploy when search behavior changes.

## Known open items

| ID / topic | Notes |
| --- | --- |
| **QA-066** | Whether **web editors** may **unpublish** or **delete** others' content is an open **product** decision. Current config does not grant web editors archive/unpublish or delete-any. |
| **Test content** | Eight **"DF Test Article"** nodes (and related automation titles) may exist from QA. Delete before launch (`df_editor` includes cleanup for `DF Test%` / `Playwright%` titles). |
| **ACSF packaging** | Install profile, site directory in multisite, and Labs merge strategy are **TBD with Jonathan Webb**. See [docs/acsf-packaging-proposal.md](acsf-packaging-proposal.md). |
| **stage_file_proxy** | Enabled in exported config for dev convenience; **disable on production**. |
| **Analytics** | `df_launch` tags are off unless enabled in config **and** the request is production (Acquia `prod`). |
| **Pre-cutover DB** | Run `drush updatedb` on the **previous** codebase before switching to this tree if the dump was taken mid-migration (see historical notes in git tag `archive/pre-acsf-handoff-47352229`). |

## Removed from this handoff branch

SearchStax demo modules, the one-time **df_migrate** pipeline, Facets, and Config Split were removed. Reference JSON remains in `df_setup/data/` for theme and search behavior. Do **not** drop `migrate_map_*` tables if present; some fallbacks still read them.

## Secrets and artifacts

Do **not** store credentials, SMTP passwords, or API keys in git. Use Acquia environment includes or per-environment `settings.php` overrides. Do **not** commit database dumps or full `sites/default/files` trees to this repository.

## Contact / next steps for ACSF

**TBD with Jonathan Webb (DFCI EWS):**

- Target Drupal and PHP versions on the shared Labs codebase.
- Which contrib packages are already pinned in Labs versus site-specific.
- Per-site config sync strategy (directory layout, splits, or other).
- Site machine name, hostname mapping in `sites.php`, and install profile naming if applicable.

Technical proposal draft: [docs/acsf-packaging-proposal.md](acsf-packaging-proposal.md).
