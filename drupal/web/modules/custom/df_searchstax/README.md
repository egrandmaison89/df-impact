# DF SearchStax

Production SearchStax integration for the `/search` route.

## What it provides

- Dedicated SearchStax page route: `/search`
- SearchStax UI kit widgets (input, results, pagination, facets, sorting, related searches, promotions)
- Optional advanced widgets (smart answers, feedback modal, location search, popular searches)
- Admin settings form: `/admin/config/search/df-searchstax`
- **Drupal → SearchStax Ingest API** indexing (structured fields, queue on save, Drush bulk push)
- SearchStax admin enablement checklist: `SEARCHSTAX_ADMIN_CHECKLIST.md`

## Secrets and environment overrides

Keep secrets in `settings.php` (or environment-injected `settings.local.php`) and avoid committing them in config exports.

```php
$settings['df_searchstax'] = [
  'search_auth' => getenv('DF_SEARCHSTAX_SEARCH_AUTH') ?: '',
  'index_auth' => getenv('DF_SEARCHSTAX_INDEX_AUTH') ?: '',
  'update_url' => getenv('DF_SEARCHSTAX_UPDATE_URL') ?: '',
  'public_base_url' => getenv('DF_SEARCHSTAX_PUBLIC_BASE_URL') ?: '',
  'track_api_key' => getenv('DF_SEARCHSTAX_TRACK_API_KEY') ?: '',
  'related_searches_api_key' => getenv('DF_SEARCHSTAX_DISCOVERY_API_KEY') ?: '',
];
```

- **`index_auth`**: SearchStax **read-write** token for the `/update` (Ingest) endpoint. **Never** use this in the browser; it stays server-side (Drush, queue worker).
- **`public_base_url`**: Canonical origin for absolute links in the index (e.g. `https://impact.example.org`). Required for good URLs when running `drush df-searchstax:push-content` outside a browser request.

Any keys present in `$settings['df_searchstax']` override stored config values.

## DDEV environment wiring

`drupal/.ddev/config.yaml` passes `DF_SEARCHSTAX_*` into the web container. Set `DF_SEARCHSTAX_INDEX_AUTH`, `DF_SEARCHSTAX_UPDATE_URL`, and `DF_SEARCHSTAX_PUBLIC_BASE_URL` in `.ddev/.env` (or your host environment), then `ddev restart` and `ddev drush cr`.

## Drupal-first indexing (recommended)

SearchStax can crawl HTML, but for Drupal the supported path is **pushing structured documents** from CMS:

1. In Studio, copy the **Update** API URL (same app as Select). Set it in config as **Update (Ingest) API URL** or `DF_SEARCHSTAX_UPDATE_URL`.
2. Create a **read-write** API token; set **`index_auth`** / `DF_SEARCHSTAX_INDEX_AUTH` (not the read-only Select token).
3. Set **Public base URL** to your real site origin so indexed links are correct.
4. Initial bulk index:

   ```bash
   ddev drush df-searchstax:push-content
   ```

5. Ongoing: saving or unpublishing supported node types queues `df_searchstax_node_index` (processed on cron).

Documents use stable Solr ids `impact-{bundle}-{nid}-{langcode}`, include `index_source_s=drupal` for separation from crawler/Future sources, and map into your app’s result card fields (`title`, body/snippet, `ss_field_story_image`, teaser/ribbon with type · date · author).

Clear only Drupal-sourced docs (optional):

```bash
ddev drush df-searchstax:delete-drupal --yes
```

If Ingest returns HTTP errors, check SearchStax logs: optional fields (`author_t`, `created_dt`, `index_source_s`) require matching dynamic fields in Solr; adjust Studio schema or simplify the payload in `SearchStaxDocumentBuilder`.

## Optional: crawler / tunnel (legacy / supplemental)

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
