# SearchStax Admin Checklist (DF Impact)

Use this checklist in the SearchStax admin console to complete production setup for the Drupal integration.

## 0) Drupal → Ingest API (recommended primary index)

- Copy the **Update** endpoint from Site Search → APIs (same deployment as Select).
- Create a **read-write** token; store only server-side as `DF_SEARCHSTAX_INDEX_AUTH` / `index_auth` (never in browser config).
- In Drupal, set **Public base URL** to the live site origin so indexed links are correct.
- Bulk push: `drush df-searchstax:push-content`; ongoing updates use the `df_searchstax_node_index` queue (cron).
- Optional: disable or narrow Studio **crawlers** once Drupal push is authoritative, to avoid duplicate/conflicting documents.
- Documents from Drupal set `index_source_s=drupal` and id `impact-{bundle}-{nid}-{lang}` for deletes and multi-source separation.

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
- facetable fields (e.g. content type, topic, date bucket)
- sortable fields (e.g. relevance, recency)

The Drupal UI templates assume these fields are present for best rendering.

## 4) Configure facets and sorting in SearchStax UI settings

Set up:

- Facet groups (content type, topics, issue/season where relevant)
- Sort options (Relevance + Newest at minimum)
- Synonyms and spelling behavior

The frontend already renders facet/sort widgets; this step controls what options appear.

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
- Filtering + sorting + pagination are correct
- Related/popular searches return useful suggestions
- Promotions trigger on configured queries
- Analytics data appears in reports within expected latency
