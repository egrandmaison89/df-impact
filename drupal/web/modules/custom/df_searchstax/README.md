# DF SearchStax

Production SearchStax **indexing** integration (no public search UI route; WP parity search uses `/search` Search API view and `/?s=`).

## What it provides

- Admin settings only (public SearchStax UI route removed; use Search API `/search` for WP `/?s=` parity)
- SearchStax UI kit widgets (input, article results, server-side Issue/Category filters, pagination, sorting, related searches, promotions)
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

SearchStax can crawl HTML, but `/search` is designed as an **article search**. The supported path is pushing structured article documents from Drupal and keeping crawler/listing pages out of this SearchStax app:

1. In Studio, copy the **Update** API URL (same app as Select). Set it in config as **Update (Ingest) API URL** or `DF_SEARCHSTAX_UPDATE_URL`.
2. Create a **read-write** API token; set **`index_auth`** / `DF_SEARCHSTAX_INDEX_AUTH` (not the read-only Select token).
3. Set **Public base URL** to your real site origin so indexed links are correct.
4. Initial bulk index:

   ```bash
   ddev drush df-searchstax:push-content
   ```

5. Ongoing: saving or unpublishing supported article nodes queues `df_searchstax_node_index` (processed on cron).

Documents use the canonical article URL as the Solr `id`, include `index_source_s=drupal` and `search_scope_s=articles`, and map into result card fields (`title`, body/snippet, `ss_field_story_image`, teaser/ribbon with article · issue · date · author). Issue and Category filters are backed by:

- `issue_id_s`, `issue_slug_s`, `issue_title_s`, `issue_url_s`
- `article_category_s`
- `content_type_s=Article`

The `/search` UI sends SearchStax filter queries for the canonical `/stories/` URL scope and for selected Issue/Category labels in the indexed result ribbon. This keeps filters server-side even before SearchStax Studio accepts custom facet fields. If Studio schema/facets are later configured for `issue_title_s` and `article_category_s`, native SearchStax facets can be enabled from the admin form.

Cleanup helpers:

```bash
ddev drush df-searchstax:delete-drupal
ddev drush df-searchstax:delete-low-value
ddev drush df-searchstax:delete-by-query "id:*category*"
```

`df-searchstax:delete-drupal` removes only old `impact-*` legacy ids. Use `delete-low-value` or `delete-by-query` for crawler URLs such as home, category, and paginated listing pages.

If Ingest returns HTTP errors, check SearchStax logs: optional fields (`author_t`, `created_dt`, `index_source_s`, `search_scope_s`, `issue_title_s`, `article_category_s`) require matching dynamic fields in Solr; adjust Studio schema or simplify the payload in `SearchStaxDocumentBuilder`.

## Optional: crawler / tunnel (legacy / supplemental)

SearchStax crawls **public** URLs. The crawler should be disabled for this app unless it has strict include/exclude rules that keep listing pages out of article search. For local DDEV, use a tunnel only when you intentionally need to inspect crawler behavior, for example:

```bash
ddev share --provider=cloudflared
```

**Important**

- Quick tunnels get a **random hostname each run** (e.g. `something.trycloudflare.com`). When you stop `ddev share` or restart it, the **old hostname stops working** (often HTTP 530). Your Drupal site at `https://df-impact.ddev.site` is unchanged; only the tunnel URL dies.
- After every new tunnel URL, update the SearchStax crawler (seed URL and/or sitemap URL) to the **new** base host. Optional: cancel a stuck crawl that still targets a dead hostname.
- Do not use a broad sitemap crawl for the article search app unless it excludes `/`, `/category/*`, `?page=*`, issue landing pages, and generic pages. Drupal push is the authoritative source for `/search`.

Check approximate index size:

```bash
ddev drush df-searchstax:index-info
ddev drush df-searchstax:index-info --show-facets
```
