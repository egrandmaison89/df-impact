# Acquia launch runbook — Dana-Farber Impact (Drupal)

Documentation only. Does not perform deploys.

## Config split (production)

| Layer | Location | Notes |
| --- | --- | --- |
| **Canonical config** | `drupal/web/sites/default/files/sync/` | Export with `drush cex -y` from staging after QA. Import on Acquia with `drush cim -y`. |
| **Secrets / overrides** | Acquia `$AH_SITE_ENVIRONMENT` settings include or `settings.php` `$config` overrides | SearchStax keys, SMTP, API keys — never commit. |
| **Site snippets** | `df_site_snippets.settings` | Optional extra header/footer HTML; prefer `df_launch.settings` for standard tags. |
| **Launch tags** | `df_launch.settings` + `LaunchEnvironment` | Tag URLs in sync; **`enabled: false`** in repo. Tags inject only when config is enabled **and** the request is a production context (see below). |
| **Front page** | `system.site:page.front` | Must be `/node/{Home}` internal path (see `df_setup`); aliases `/homepage`, `/` served via subscribers. |
| **Performance** | `system.performance` | `css.preprocess` + `js.preprocess` **true** on Acquia (aggregated assets). |

**Pre-cutover checklist:** `drush updatedb -y`, `drush cim -y`, `drush cr`, `drush simple-sitemap:generate`, `drush search-api:index df_impact_content`.

### Analytics tags (`df_launch`) — production only

Third-party tags (Adobe Launch, OneTrust, Meta pixel, Bing UET, Jetpack stats) **must not** load on DDEV, cloud-agent VMs, or Acquia **dev/test/stage**. Injection requires **both**:

1. `df_launch.settings:enabled` is `TRUE`, and  
2. **Production context** — `getenv('AH_SITE_ENVIRONMENT') === 'prod'` **or** (non-Acquia prod only) `$settings['df_launch_tags_enabled'] = TRUE` in `settings.php` (defaults to **off**).

**Acquia production** (recommended in `sites/g`/prod `settings.php` or Acquia include):

```php
if (getenv('AH_SITE_ENVIRONMENT') === 'prod') {
  $config['df_launch.settings']['enabled'] = TRUE;
}
```

**Verify on non-prod:** View source — no `launch-90664c3f0ed7.min.js`, `otSDKStub.js`, `fbevents.js`, or `bat.bing.com/bat.js`.

**Automated test:** `cd drupal && ./vendor/bin/phpunit -c web/modules/custom/df_launch/phpunit.xml.dist`

## Files sync (`wp-images`)

WordPress media parity lives under:

`drupal/web/sites/default/files/wp-images/`

| Step | Action |
| --- | --- |
| Source of truth | Migration exports + `df_migrate` scripts (`restore_wp_images.php`, etc.) |
| Deploy | Rsync or Acquia files sync from staging → prod (exclude `php`, `css/js` aggregates) |
| Legacy URLs | `/wp-content/uploads/*` → internal rewrite to `wp-images` (`df_secondary`) |
| Verify | Spot-check article hero images and Photon-style URLs on prod domain |

## Search index

| Item | Detail |
| --- | --- |
| Backend | Search API index `df_impact_content` (DB backend in dev; confirm Acquia backend) |
| Public UI | View `df_impact_search` at `/search`; `/?s=` rewrite via `df_secondary` |
| SearchStax | Indexing/admin only (`df_searchstax`); no public `/search` Studio UI |
| Reindex | After content deploy: `drush search-api:clear df_impact_content && drush search-api:index df_impact_content` |
| Cron | Ensure `search_api_cron` / queue workers run on Acquia cron |

## Cron

Acquia cron should invoke Drupal cron (`drush cron` or `/cron/{key}`):

- Search API pending items  
- `simple_sitemap` generation (weekly or post-deploy)  
- `df_searchstax` node index queue (if enabled)  
- Core `automated_cron` disabled in config — rely on Acquia schedule  

## DNS cutover checklist

1. **Freeze** WP content or accept final delta migration window.  
2. **Stage** full `cim`, files sync, search reindex, sitemap generate on Acquia prod.  
3. **Smoke** on Acquia URL (hosts file or temp domain): `parity:smoke:200`, `parity:url:check`, template samples.  
4. **Lower TTL** on `danafarberimpact.org` DNS (24h → 300s) 48h before cutover.  
5. **Point** apex/`www` A/AAAA or CNAME to Acquia edge per hosting doc.  
6. **Verify** HTTPS cert, `robots.txt`, `sitemap_index.xml`, redirects (`?p=`, feeds).  
7. **Tags** — on prod only, confirm Adobe Launch + OneTrust load; re-check dev/stage still have **no** tags.  
8. **Monitor** 404 rate, origin 5xx, Search Console, analytics dashboards (24–72h).  

## Rollback

| Scenario | Action |
| --- | --- |
| **DNS rollback** | Revert DNS to WordPress origin; TTL already low. Drupal stays read-only. |
| **Code rollback** | Acquia: redeploy previous tag/branch; `drush cim` only if config compatible. |
| **Config rollback** | Restore prior config tarball from git tag; `drush cim -y`. |
| **Files** | Do not delete `wp-images` on rollback; WP may still reference uploads paths. |
| **Search** | Reindex after rollback deploy if content changed. |

## Post-launch parity gates (CI / manual)

```bash
cd parity
DRUPAL_BASE_URL=https://danafarberimpact.org npm run parity:smoke:200
DRUPAL_BASE_URL=https://danafarberimpact.org npm run parity:head:refpack
DRUPAL_BASE_URL=https://danafarberimpact.org npm run parity:url:check
```

Launch readiness (optional): `parity:launch:lighthouse`, `parity:launch:axe`.
