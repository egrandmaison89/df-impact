# Phase 2 — ACSF packaging proposal

Not implemented. Phase 1 leaves a single-site Drupal tree that runs as it does today. This note is how we would fold that tree into the Labs ACSF codebase, and how the custom modules could be consolidated later.

Labs adds a site to a shared codebase as an install profile plus a sites directory, with `sites.php` routing and a config sync strategy. Everything we require becomes a package in that shared codebase, so the profile should contain only what Impact needs.

## What would move

| Piece | Proposed location | Notes |
| --- | --- | --- |
| Custom modules | `web/modules/custom/df_*` (or the Labs custom-modules path) | Keep the six modules from Phase 1. Do not copy `drupal/web/core` or `drupal/vendor`. |
| Theme | `web/themes/custom/df_impact` | Includes vendored `wp-assets/`. |
| Install profile | `web/profiles/custom/<labs-name>` | Machine name is an open question. |
| Site directory | `web/sites/<site>` | `settings.php`, `services.yml` if needed, hash salt and DB from the factory. |
| Config | outside the webroot, one directory for this site | Today: `drupal/config/sync`. |
| Routing | `sites.php` | Map the Impact hostname to the site directory. ACSF may generate this. |

`composer.json` requirements would be added to the Labs root `composer.json`, then resolved once. Direct requirements after Phase 1 are the modules listed in `drupal/composer.json` `require`, minus Drush if Labs already pins it. Transitive packages that must remain available: `drupal/ctools` (Pathauto), `drupal/crop` (Focal Point), `drupal/gin_toolbar` (Gin), `drupal/entity_reference_revisions` (Paragraphs).

Core and contrib under `drupal/web/` stay committed in this repository so the tree still runs standalone. The Labs codebase should not take a second copy of core. Phase 2 stops committing `web/core`, `web/modules/contrib`, and `web/themes/contrib` in the handoff branch only if Labs confirms their build runs `composer install`. That is a separate change from Phase 1, which stops committing `vendor/` only.

## Install profile

The profile would:

- Depend on the six custom modules, `df_impact`, and the contrib modules enabled in `config/sync/core.extension.yml`.
- Ship or point at the Impact config sync directory as the profile's default config, so a factory install enables the same modules, fields, views, and theme.
- Not re-import WordPress. `df_setup_install()` creates a Home page and an About page only when they are missing. On a database dump those hooks do not run again, because the modules are already installed.

The handoff path is a dump plus files, then `updatedb`, `cim`, and `cr`. A brand-new profile install produces the content model and an empty Home node. It does not produce the magazine. Do not use a fresh install as a substitute for the dump.

Profile install config and the committed sync directory must be the same export. Two copies will drift. Prefer one directory that both the profile and `settings.php` reference.

## Sites directory and sites.php

```php
// sites.php (illustrative)
$sites['impact.example.edu'] = '<site-directory>';
```

`settings.php` in that directory should keep config outside the docroot, the same way this repo does:

```php
$settings['config_sync_directory'] = dirname(__DIR__, 3) . '/config/<site>';
```

The factory docroot layout may be different. The invariant is: not under `sites/<site>/files/`.

`stage_file_proxy` is enabled in the current export. The site directory, or an environment include, should force it off in production. That override is a Labs choice. Phase 1 does not change the exported value, because turning it off changes local development.

`df_launch` already refuses to inject tags unless `AH_SITE_ENVIRONMENT` is `prod` and the config flag is enabled. Set the flag in the prod settings include, not in the exported config that every environment imports.

## Config sync

Three options, in the order we would take them:

1. **Per-site sync directory.** One folder for Impact, imported on that site's deploy. Matches this repo. No extra module. Other sites keep their own directories. This is the recommendation until Labs says otherwise.
2. **Config Split.** Useful when one sync directory is shared and only environment or site deltas differ. We removed an unused Config Split install in Phase 1. Add it back only if Labs already uses it and wants Impact's prod-only pieces (`stage_file_proxy` off, launch tags on) expressed as splits.
3. **Features / a second profile config snapshot.** A second copy of the same YAML. We would not do this.

Multisite config sync fails when two sites share one sync directory and one site's `cim` writes the other's `system.site` UUID, theme, or front page. Impact needs its own directory and its own site UUID.

## Custom code consolidation

Do this after the profile name and directory layout are agreed. Doing it in Phase 1 would churn every class the theme calls, for no gain in the Labs review.

Current boundaries:

| Module | Keep separate because |
| --- | --- |
| `df_setup` | Content model behavior: front page, archives, legacy paths, and the reference JSON. Largest module. |
| `df_secondary` | Request-time edge: `/?s=`, feeds, redirects, upload paths. Small and easy to find. |
| `df_metatag` | Head tags only. Depends on Metatag, not on the edge module. |
| `df_editor` | Editorial workflow and chrome settings. Depends on `df_setup` and `df_site_snippets`. |
| `df_launch` | Production tag policy (Acquia environment gate). |
| `df_site_snippets` | Editor-entered HTML. Different trust boundary from `df_launch`. |

Overlap worth deciding with Labs, not worth merging blindly:

- **Tags.** `df_launch`, `google_tag`, and `google_analytics` can all print third-party tags. `df_launch` is the WordPress parity path and is production-gated. The Google modules are enabled with whatever their config says. Recommendation: ask Labs which tag stack the shared platform uses. If the answer is Adobe Launch plus OneTrust, drop the Google modules from the profile. If the answer is GTM, fold the Launch snippets into that and drop `df_launch`. Do not enable all three in production by default.
- **Snippets vs launch.** `df_site_snippets` is arbitrary trusted HTML. `df_launch` is structured tags. Merging them would put an editor HTML field on the same module as the prod-only gate, which makes the gate easier to bypass. Leave them separate.
- **`df_setup` + `df_secondary` + `df_metatag`.** They share `df_setup/data/*.json`. A single `df_impact` module would match a profile named `df_impact` and remove two info files. Cost: a large module, and every theme function that calls `df_setup` or `df_metatag` services would move. Recommendation: keep three modules inside the profile. Revisit only if Labs wants one custom project per site.
- **Naming.** `df_*` is local shorthand. Labs may want a prefix that will not collide with another site's custom modules (`dfci_impact_*` or similar). Renaming is a mechanical namespace change plus an update hook that uninstalls the old names and installs the new ones, or a one-time `hook_update` in the profile. Do it once, in the profile PR, not before the name is chosen.

## Tradeoffs

| Choice | Gain | Cost |
| --- | --- | --- |
| Profile + site directory, modules stay as they are | Small diff from Phase 1. Labs can review the profile without relearning the PHP. | Six custom modules enter the shared tree. |
| Merge custom modules first | Fewer directories. | High conflict risk with the theme and with `df_setup` update hooks. No change in what ACSF has to enable. |
| Commit a built docroot (`vendor/`, `core/`, contrib) into Labs | Deploy without Composer. | Duplicates core and contrib the factory already has. Phase 1 already stopped committing `vendor/`. |
| Fresh install from the profile instead of a dump | No dump to manage. | Loses the magazine. `df_setup_install()` only seeds Home and About. |
| Put config back under `sites/default/files` | Familiar to older Drupal checklists. | Config is inside the public files tree. Phase 1 moved it out on purpose. |

## Suggested Phase 2 sequence

1. Confirm core, PHP, contrib versions, profile name, and the config directory convention with Labs.
2. Add an install profile that enables the current module list and uses `config/sync` as its default config. Do not rename modules in that pull request.
3. Add the site directory and `sites.php` entry on a Labs branch. Point `config_sync_directory` at the Impact directory.
4. Import the dump and files. `updatedb`, `cim`, `cr`. Confirm `/?s=cancer` and a few article, issue, and archive URLs.
5. Decide tag modules and `stage_file_proxy` with Labs, as config overrides, not as a module merge.
6. Only then consider renaming or merging custom modules, with update hooks, on a follow-up.

## Explicitly out of scope

- Rewriting the theme.
- Re-enabling SearchStax or Facets.
- Restoring the WordPress migration as a supported install path.
- Copying the parity harness into the Labs repo. It stays on `archive/pre-acsf-handoff-47352229` for QA.
