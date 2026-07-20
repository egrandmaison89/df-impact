# SearchStax Admin Checklist (DF Impact)

Use this checklist in the SearchStax admin console to complete production setup for the Drupal integration.

## 0) Drupal → Ingest API (recommended primary index)

- Copy the **Update** endpoint from Site Search → APIs (same deployment as Select).
- Create a **read-write** token; store only server-side as `DF_SEARCHSTAX_INDEX_AUTH` / `index_auth` (never in browser config).
- In Drupal, set **Public base URL** to the live site origin so indexed links are correct.
- Bulk push: `drush df-searchstax:push-content`; ongoing article updates use the `df_searchstax_node_index` queue (cron).
- Disable or narrowly scope Studio **crawlers**. The `/search` experience is article-first and should not include `/`, `/category/*`, `?page=*`, issue landing pages, or generic pages.
- Documents from Drupal use canonical article URLs as ids and include Issue/Category labels in the stored ribbon. The UI filters every query to the canonical `/stories/` URL scope so crawler listing pages are excluded even before custom fields are available in the Solr schema.

## 1) Confirm API endpoints and app metadata

In **Site Search > API Integrations**, confirm for app `2176`:

- Select API endpoint (`/emselect`)
- Auto-suggest endpoint (`/emsuggest`)
- Update API endpoint (`/update`) for backend indexing work
- Related searches endpoint (`/related-search/`)
- Popular searches endpoint (`/popular-search/`)
- Analytics base URL (`https://analytics-us.searchstax.com`)

## 2) Create/rotate API keys and tokens

Create or rotate:

- **Read-only token** for Select + Suggest APIs
- **Discovery API key** for Related + Popular (+ geocoding if used)
- **Analytics tracking key**

Keep these out of committed config. Put them in `settings.php` overrides:

```php
$settings['df_searchstax'] = [
  'search_auth' => 'READ_ONLY_TOKEN',
  'track_api_key' => 'ANALYTICS_TRACKING_KEY',
  'related_searches_api_key' => 'DISCOVERY_API_KEY',
  // Optional if enabled:
  // 'question_url' => 'https://search-ai-us.searchstax.com/api/v1/2176/answer/',
];
```

## 3) Configure result schema/mappings for UI features

In SearchStax schema/display config ensure search results include:

- `title` (required)
- canonical `url` (required)
- `description` / snippet (for result summaries)
- `thumbnail` (for rich cards)
- stored result ribbon (`tcngramm_X3b_en_field_story_teaser`) containing Article, Issue, Category, date, and author
- optional facetable fields for future native facets: `issue_title_s` and `article_category_s`
- sortable fields (e.g. relevance, recency)

The Drupal UI templates assume these fields are present for best rendering.

## 4) Configure facets and sorting in SearchStax UI settings

Set up:

- Keep **Article filters (Issue and Category)** enabled under `/admin/config/search/df-searchstax`. These are server-side filter queries against article URL scope and ribbon text.
- Keep **SearchStax native facets** disabled until Solr/Studio accepts and returns `issue_title_s` and `article_category_s`.
- Optional future native facet groups: **Issue** → `issue_title_s`, **Category** → `article_category_s`.
- Sort options (Relevance + Newest at minimum)
- Synonyms and spelling behavior

After changing indexed fields, run **`drush df-searchstax:push-content`** (or wait for cron queue). The SearchStax facets widget uses Studio templates (desktop sidebar + mobile overlay); styling lives in `df_searchstax.css`.

Drupal sends (after `drush df-searchstax:push-content`):

- `index_source_s` — `drupal`
- `search_scope_s` — `articles`
- `content_type_s` — `Article`
- `issue_id_s`, `issue_slug_s`, `issue_title_s`, `issue_url_s`
- `article_category_s`
- `topic_ss` and `cancer_type_ss` remain available for future facets but are not part of the primary UI.

Add these as **non-tokenized string** fields in your collection schema if they are not already accepted by dynamic rules, then use **Site Search → Faceting** to create facet groups pointing at `issue_title_s` and `article_category_s`. Reload schema in the Faceting UI if fields do not appear. Until then, the Drupal article filter sidebar does not depend on SearchStax facet buckets.

Verify with `drush df-searchstax:index-info --show-facets` (facet buckets populate only after documents contain those fields and Solr stores them).

Cleanup before final QA:

```bash
drush df-searchstax:delete-drupal
drush df-searchstax:delete-low-value
drush df-searchstax:push-content
drush df-searchstax:index-info --show-facets
```

## 5) Enable and tune Discovery features

Enable and validate:

- Related searches suggestions
- Popular searches feed
- External promotions/business rules for important queries

These are wired in Drupal and will appear when the APIs return data.

## 6) Enable analytics and validate event flow

In SearchStax analytics:

- Confirm query and click events are arriving from `/search`
- Validate filter/sort/pagination interactions are tracked
- Verify session trends after go-live

## 7) Optional: AI answer widget

If your subscription/app enables Search AI answers:

- Confirm answer endpoint (`/answer/`) for app `2176`
- Add `question_url` in Drupal settings
- Validate answer quality and fallback behavior

If not enabled, leave `question_url` blank and disable `answer_widget` in Drupal config.

## 8) Optional: geolocation search

If location search is needed:

- Enable `location_search` in Drupal config
- Validate geocoding with Discovery key
- Confirm country code (`us`) and app id are correct

## 9) Go-live verification matrix

Before production cutover, test in SearchStax + Drupal:

- Autosuggest returns expected queries
- Issue and Category filtering apply to the full result set with correct counts, sorting, and pagination
- Homepage, category pages, category pagination, issue landing pages, and generic pages are absent
- Related/popular searches return useful suggestions
- Promotions trigger on configured queries
- Analytics data appears in reports within expected latency
