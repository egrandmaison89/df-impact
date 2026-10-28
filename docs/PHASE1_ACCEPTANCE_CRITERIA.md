# Phase 1 acceptance criteria & phased fix plan

## Proposed thresholds (“done” for visual rebuild)

These apply **per template family** after fixes, measured by `parity/` harness on **desktop 1440** unless noted.

| Check | Threshold | Notes |
| --- | --- | --- |
| Pixel diff (full page) | **≤ 8%** median, **≤ 15%** p90 per template | After masking dynamic ads/analytics if any; cookie banners disabled in harness |
| Pixel diff (tablet/mobile) | **≤ 12%** median | Layout reflow expected; stricter on typography components |
| Text similarity (main content) | **≥ 92%** token overlap | Headings list compared separately; empty main = fail |
| Heading structure | **100%** match of h1 text; h2 order ≥ 90% | Article/issue templates |
| URL path parity | **100%** manifest paths resolve on Drupal (200 or documented 301) | Redirects imported via `redirects.json` |
| Page title | **100%** match normalized | |
| Meta description | **≥ 95%** match on articles/issues | |
| Broken asset links | **0** critical (hero, logo, cover); **≤ 3** minor per page | |
| axe violations | Informational; **0 critical** on homepage + one article | Not a launch blocker in Phase 1 |
| Lighthouse performance | Informational; track LCP trend | Baseline in Phase 2 |

## Sample vs full runs

- **PR / dev:** `npm run parity:sample` (~25–40 URLs, all templates represented)
- **Pre-release:** `npm run parity:full` on merged manifest (~650+ URLs)

## Phased fix plan (Phase 2+)

Ordered by leverage (component batches):

1. **Global chrome** — Header, footer, primary nav, typography tokens (font-family, scale, link colors). Unblocks every template.
2. **Homepage** — Hero, featured story, issue promo, grid blocks.
3. **Issue template** — Cover, masthead, article list/grid, season label.
4. **Article template** — Byline/credit, body styles (drop cap, pull quote), featured image styles, issue breadcrumb.
5. **In Brief template** — Card density, thumbnails, section headers.
6. **Archives / Views** — Topics, stories index, pagination/infinite scroll.
7. **Search** — SearchStax UI vs WP search (if in scope).
8. **Redirects & edge URLs** — Remaining 301 gaps from manifest.

Each batch: fix → run targeted parity subset → update gap register.

## Content / editorial acceptance

- Editor can create **Issue** node, upload cover, assign **articles** via fields, publish without developer (**manual UAT checklist** — automate in Phase 3).
- `fetch_wordpress_model.py` + `migrate:import --update` documented as the **content refresh** path.
- No public **forms** to replicate (contact/subscribe) per product scope.
