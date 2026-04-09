# Dana-Farber Impact Magazine — Engineering Standup
**Date:** April 1, 2026
**Project:** WordPress → Drupal 11 Rebuild
**Stack:** Drupal 11 · DDEV · Composer · Search API DB · Custom Migrate plugins

---

## What We Shipped

### Phase D — Content Migration (Complete)
Fully automated WordPress → Drupal migration using the Drupal Migrate API and a custom Python processing pipeline.

**Data pipeline:**
- Fetched all 649 WordPress posts via REST API (`/wp-json/wp/v2/posts?_embed`)
- Python script classified posts into content types by matching tag patterns (`^In Brief\s+(Spring|Summer|Fall|Winter)\s+\d{4}`)
- Output 7 structured JSON source files consumed by Drupal migration YMLs

**Custom Migrate process plugins written:**
| Plugin | What it does |
|--------|-------------|
| `df_map_issue_name` | Resolves "Spring 2026" → `field_season` + `field_year` node lookup |
| `df_map_term_names` | Maps WP category/tag arrays → taxonomy term IDs (cached per vocabulary) |
| `df_download_image` | Downloads WP media URLs → `public://wp-images/`, deduplicates by URI |

**Migration results:**

| Content Type | Count |
|---|---|
| Articles | **382** published |
| In Brief items | **276** published |
| Issues | **14** published |
| Media images downloaded | 470 |
| URL redirects imported | **661** |
| Search index | **673/673** (100%) |

**Key fixes landed:**
- Content Moderation overrides `status=1` — fixed by adding `moderation_state: published` to migration process maps
- `search_api_db` backend required `'database' => 'default:default'` in `backend_config` — not documented, found via source inspection
- Tracker item ID format is `NID:en` not `en/NID` — confirmed via datasource `getItemIds()` pagination

---

### Phase E — Integration & Polish (Complete)

#### Search
- Search API with Database backend, 673 nodes indexed at 100%
- Full-text search across: title (5× boost), subtitle (3×), excerpt (2×), body, byline
- `/search` page built as a Views display using `search_api` row plugin per-bundle view modes
- Search icon added to all page headers; results render as themed card components

#### Performance
- CSS/JS aggregation enabled — output is hashed aggregate bundles
- Page cache set to 900s
- Twig debug disabled (was on during dev)

#### Security
Layered defense: application-level (seckit module) + web server-level (nginx):

```
Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'
  https://www.googletagmanager.com https://www.google-analytics.com;
  object-src 'none'; frame-src 'none'; ...
X-Frame-Options: SAMEORIGIN
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), microphone=(), geolocation=()
```

Nginx headers live in `.ddev/nginx/security-headers.conf` (survives container restarts by using the `include /mnt/ddev_config/nginx/*.conf` hook rather than the `#ddev-generated` config file).

#### URL Redirects
661 WP-to-Drupal 301 redirects active. Pattern:
```
/slug/          →  301  →  /stories/slug
/in-brief-slug/ →  301  →  /in-brief/slug
/category/name  →  301  →  /topics/name
```

---

## 2026-04-08 update

- **Documentation:** `DRUPAL_REBUILD_PLAN.md` §5.2/§5.3 and Appendix A reconciled with this file; root markdown is no longer gitignored.
- **Agent handoff:** See `docs/AGENT_HANDOFF.md`; stakeholder admin options in `docs/ADMIN_UX_OPTIONS.md`.
- **Front-end:** Article body CSS extended for common WordPress block markup; article template shows photo credit once (hero caption).
- **Site snippets:** Optional module `df_site_snippets` — enable with `drush en df_site_snippets`, then **Configuration → System → Site snippets** (trusted roles only).

## What's Left Before Launch

| Item | Status | Notes |
|------|--------|-------|
| Google Tag Manager | Waiting on client | Script ready at `scripts/configure-gtm.php` — needs `GTM-XXXXXXX` |
| HSTS | Blocked on prod SSL | Enable in seckit once cert is live |
| DNS cutover | Not started | Point `impact.dana-farber.org` → new host; set `$base_url` in `settings.php` |
| Faceted search | Deferred | `facets` module installed; facet blocks can be added post-launch |
| Editorial workflow QA | In progress | Content Moderation configured; needs editor sign-off |

---

## Site Health Snapshot

```
GET /                           200  ✅
GET /issues                     200  ✅  (Browse Issues grid)
GET /in-brief                   200  ✅
GET /search?q=cancer            200  ✅  (10 relevance-ranked cards)
GET /topics/cancer-research     200  ✅
GET /slug/                      301  ✅  → /stories/slug
GET /category/cancer-research   301  ✅  → /topics/cancer-research
```

---

## Engineering Notes / Decisions Worth Documenting

- **Migration is idempotent**: re-running `drush migrate:import` skips already-imported items by source ID hash. Safe to re-run for incremental updates pre-launch.
- **Content Moderation + migration**: any future migration that imports nodes must set **both** `status` and `moderation_state` in the process map — setting only `status` is silently overridden.
- **Search API DB tracker bootstrap**: newly created indexes show 0 items tracked until you either save a node or manually call `Index::trackItemsInserted($datasource_id, $item_ids)`. The `drush search-api:reset-tracker` + `drush search-api:index` workflow only works after the tracker has been primed.
- **Nginx security headers in DDEV**: the `nginx_full/nginx-site.conf` file is regenerated by DDEV on restart if it contains `#ddev-generated`. Custom headers belong in a separate file under `.ddev/nginx/` which is included via `include /mnt/ddev_config/nginx/*.conf`.
