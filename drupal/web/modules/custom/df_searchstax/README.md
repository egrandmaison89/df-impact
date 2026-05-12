# DF SearchStax

Production SearchStax integration for the `/search` route.

## What it provides

- Dedicated SearchStax page route: `/search`
- SearchStax UI kit widgets (input, results, pagination, facets, sorting, related searches, promotions)
- Optional advanced widgets (smart answers, feedback modal, location search, popular searches)
- Admin settings form: `/admin/config/search/df-searchstax`
- SearchStax admin enablement checklist: `SEARCHSTAX_ADMIN_CHECKLIST.md`

## Secrets and environment overrides

Keep secrets in `settings.php` (or environment-injected `settings.local.php`) and avoid committing them in config exports.

```php
$settings['df_searchstax'] = [
  'search_auth' => getenv('DF_SEARCHSTAX_SEARCH_AUTH') ?: '',
  'track_api_key' => getenv('DF_SEARCHSTAX_TRACK_API_KEY') ?: '',
  'related_searches_api_key' => getenv('DF_SEARCHSTAX_DISCOVERY_API_KEY') ?: '',
];
```

Any keys present in `$settings['df_searchstax']` override stored config values.

## DDEV environment wiring

This project maps `DF_SEARCHSTAX_*` variables from `.ddev/config.yaml` into the
web container. To configure locally:

1. Copy `.ddev/.env.searchstax.example` to `.ddev/.env`
2. Fill in your keys/tokens
3. Run `ddev restart`
4. Run `ddev drush cr`

## Local indexing for SearchStax (cloudflared / `trycloudflare.com`)

SearchStax crawls **public** URLs. For local DDEV, use a tunnel, for example:

```bash
ddev share --provider=cloudflared
```

**Important**

- Quick tunnels get a **random hostname each run** (e.g. `something.trycloudflare.com`). When you stop `ddev share` or restart it, the **old hostname stops working** (often HTTP 530). Your Drupal site at `https://df-impact.ddev.site` is unchanged; only the tunnel URL dies.
- After every new tunnel URL, update the SearchStax crawler (seed URL and/or sitemap URL) to the **new** base host. Optional: cancel a stuck crawl that still targets a dead hostname.
- Using **`https://<tunnel-host>/sitemap.xml`** as the crawl source is valid: the crawler reads the sitemap and follows listed URLs.

Check approximate index size:

```bash
ddev drush df-searchstax:index-info
```
