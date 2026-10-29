# Secondary surfaces parity

PR **#18** (base `cursor/phase4-chrome-behavior-0b67`, includes merge of PR #5).

Comparison target: live [danafarberimpact.org](https://danafarberimpact.org) and `parity/reference-pack/` when WP returns bot walls.

| Surface | WordPress | Drupal | Status |
| --- | --- | --- | --- |
| Search (`/?s=`, results, pagination) | `/?s={term}` → Better Search results (`search search-results` body classes); ~10 results per page | `df_secondary` rewrites `/?s=` → `/search?s=`; Search API view; card grid `.search-results .card`; title “You searched for {term}” | **Parity** (see search evidence; index required) |
| 404 page | Centered heading + search prompt (`error404`) | `page--404.html.twig` + body classes / metatags | **Parity** |
| RSS | `/feed`, category feeds; absolute item links | `FeedController::siteFeed` + category/node feeds; production `https://danafarberimpact.org` links | **Parity** (W3C-style checks below) |
| robots / sitemap / legacy | Yoast `robots.txt`, `sitemap_index.xml`, `?p=`, uploads, author/tag/page | `robots.txt`, subscriber aliases, `WordPressIdMap`, `df_setup` tag/category pagination | **Parity** |
| Trailing slashes | Permalinks/category archives canonical with `/` (live often 301 no-slash → slash; bot-dependent) | Internal rewrite to trailing-slash route + `redirect.settings:route_normalizer_enabled: false`; manifest paths stay **200 same-path** | **Parity** (canonical in metatags/RSS; no manifest-breaking 301) |
| Share / print | Social Snap URLs + `@media print` | `article-social-share.html.twig` + theme CSS | **Parity** |
| Favicons | 32/192, apple-touch, tile | `df_secondary_page_attachments` | **Parity** |
| SearchStax UI | **Not** on public WP search (`/?s=`) | Public `/search-stax` **removed**; module kept for **indexing/admin** only (`/admin/config/search/df-searchstax`) | **Correct** |

## 1) `system.site:page.front` → `/node/795`

| Check | Result | Evidence |
| --- | --- | --- |
| Home node | `node/795` title **Home** (`page` bundle) — matches `df_setup` `_ensure_home_landing()` | `drush sqlq` |
| `/` vs `/homepage` main region | **Same** SHA-256 prefix of `<main id="wp--skip-link--target">` | `3efbcea7a877d0b2` both paths |
| Hero video | **Present** on `/` and `/homepage` | `hero_present: true` both |
| `/homepage` rendering | Internal rewrite to `/` front route (`WordpressHomepageRouteSubscriber`) so `isFrontPage()` hero uses **`df_impact_get_latest_issue_nid()`** / **`df_setup_get_latest_issue_nid()`**, not a pinned issue node | `df_setup/src/EventSubscriber/WordpressHomepageRouteSubscriber.php` |
| Publish workflow | **`drush df-editor:test-publish-workflow` passed** (latest issue restored) | VM run after front fix |

Front page is the **Home** landing node; featured issue/hero updates when a newer issue is published — **not** tied to a fixed issue nid in `page.front`.

## 2) Search (`/?s=cancer`)

| Metric | Drupal (`/search?s=cancer`) | WP live (`/?s=cancer`) |
| --- | --- | --- |
| Document title | You searched for cancer - Impact Magazine | (bot wall intermittent) |
| Result cards | **10** (`.search-results .card`) | **10** when reachable |
| Body classes | `search search-results error404` | `search search-results` |
| Pagination | Present when >10 hits (`js-pager__items`) | WP theme pager when applicable |

**Screenshots:** `parity/reports/progress/secondary-surfaces/search-cancer-drupal.png`, `search-cancer-wp-live.png`  
**JSON:** `search-cancer-compare-1793254958893.json`  
**Script:** `parity/scripts/compare-search-cancer.mjs`

**SearchStax:** Removed public `/search-stax` route. WP search is core/Better Search at `/?s=`; SearchStax remains backend indexing (see `df_searchstax` README).

**Deploy note:** run `drush search-api:index df_impact_content` so `/search?s=` returns results.

## 3) Trailing slashes

- **WP (sampled):** some article paths **301** no-slash → slash; others **200** on both (bot/CDN dependent).
- **Drupal:** `df_secondary` internally rewrites eligible paths to trailing-slash form; **`route_normalizer_enabled: false`** prevents Drupal from stripping slashes.
- **`parity:url:check`:** unchanged expectations — **801/801** (`check-1793256037489.json`).
- **`parity:head:refpack`:** follows one Drupal 301 when present (`compare-head-meta.mjs`); **21/21** (`compare-1793255260395.json`).

## 4) RSS (W3C feed validator rules — well-formed XML + absolute links)

| Feed | Well-formed | Sample item link |
| --- | --- | --- |
| `/rss.xml` | yes | `https://danafarberimpact.org/2026/09/a-new-era-in-cancer-care-lavine-bekenstein-cancer-hospital-at-dana-farber/` |
| `/feed` | yes (via site feed route) | same |
| `/category/cancer-research/feed` | yes | `https://danafarberimpact.org/2026/09/yawkey-foundation-.../` |

## Gates (before push, `DRUPAL_BASE_URL=http://127.0.0.1:8888`)

| Gate | Result | Artifact |
| --- | --- | --- |
| `npm run parity:smoke:200` | pass (39 paths, 0×5xx) | `smoke-1793255259214.json` |
| `npm run parity:head:refpack` | **21/21** | `compare-1793255260395.json` |
| `npm run parity:url:check` | **801/801** | `check-1793256037489.json` |
