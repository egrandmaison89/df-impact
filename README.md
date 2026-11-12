# Dana-Farber Impact (Drupal)

Rebuilt Drupal site for [danafarberimpact.org](https://danafarberimpact.org). The project is a full site codebase prepared for handoff to DFCI Enterprise Web Solutions for placement in the shared Acquia Cloud Site Factory (ACSF) Labs repository. The site is delivered as this repository, a database dump, and the public files tree. It is not rebuilt from WordPress at deploy time.

## Audience

- **Operators and integrators:** start with [docs/HANDOFF.md](docs/HANDOFF.md) (Jonathan W. Webb, DFCI EWS).
- **Editors:** [docs/editors/WEB_EDITOR_GUIDE.md](docs/editors/WEB_EDITOR_GUIDE.md) and [docs/editors/ADMIN_GUIDE.md](docs/editors/ADMIN_GUIDE.md).
- **Launch on Acquia:** [docs/deploy/ACQUIA_RUNBOOK.md](docs/deploy/ACQUIA_RUNBOOK.md).

## Stack (summary)

| Item | Value |
| --- | --- |
| Drupal | 11.4.x (`drupal/core-recommended` in `drupal/composer.json`) |
| PHP | 8.3+ |
| Database | MariaDB 11.8 in local DDEV; MySQL 8 compatible |
| Docroot | `drupal/web` |
| Public theme | `df_impact` (custom) |
| Admin theme | Gin |
| Search | Search API with the **database** backend (`search_api_db`), index `df_impact_content` |
| Config sync | `drupal/config/sync` (outside the webroot) |

Custom modules live under `drupal/web/modules/custom/` (`df_setup`, `df_secondary`, `df_metatag`, `df_editor`, `df_launch`, `df_site_snippets`). See [docs/HANDOFF.md](docs/HANDOFF.md) for roles, workflow, and deploy steps.

## Repository layout

```
drupal/                 Composer project (vendor not committed; run composer install)
  config/sync/          Canonical configuration export
  web/                  Docroot (index.php, core, modules, themes, sites/default)
  .ddev/                Local DDEV definition (optional; from drupal/)
docs/                   Handoff, editor, and deploy documentation
scripts/                One-off and repro helpers (not runtime)
HANDOFF.md              Short pointer to docs/HANDOFF.md
```

## Quick start (DDEV)

From `drupal/`:

```bash
ddev start
ddev composer install
```

Import the database dump and files per [docs/HANDOFF.md](docs/HANDOFF.md), then:

```bash
ddev drush updatedb -y
ddev drush cim -y
ddev drush cr
ddev drush search-api:clear df_impact_content
ddev drush search-api:index df_impact_content
```

PHPUnit (no database required for unit tests):

```bash
cd drupal
./vendor/bin/phpunit -c web/modules/custom/df_launch/phpunit.xml.dist
./vendor/bin/phpunit -c web/modules/custom/df_editor/phpunit.xml.dist --testsuite df_editor --filter Unit
```

## ACSF / Labs

Packaging as an ACSF site or install profile is **not** implemented in this tree. Open questions for DFCI EWS (Jonathan Webb) are listed in [docs/HANDOFF.md](docs/HANDOFF.md) and [docs/acsf-packaging-proposal.md](docs/acsf-packaging-proposal.md).

## What not to commit

Do not commit production secrets, database dumps, local `settings.local.php`, or `drupal/vendor/`. Do not commit generated aggregates under `sites/default/files/css` or `js`.
