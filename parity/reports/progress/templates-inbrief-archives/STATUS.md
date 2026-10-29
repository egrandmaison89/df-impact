# templates-inbrief-archives agent status

Branch: `cursor/templates-inbrief-archives-185b`  
Base: `cursor/phase4-chrome-behavior-0b67`  
PR: [#13](https://github.com/egrandmaison89/df-impact/pull/13)

## Owner manual QA (DDEV) — fixes landed

| # | Issue | Fix |
| --- | --- | --- |
| 1 | Pagination plain bulleted list | WP `wp-block-query-pagination` via `pager.html.twig` + `pager--wp-query.html.twig`, `css/wp-query-pagination.css`, all Views listing routes |
| 2 | In Brief band white-on-grey | `.stk-c3abd9d` navy background + white type (matches WP Stackable row) |
| 3 | Browse Issues grid/covers | Cover art from `field_cover_image`, 250px `figure.issue-cover`, 4-column flex grid CSS |
| 4 | `&#039;` in titles | `TextEntityHelper`, migrate `df_decode_plain_text`, drush/php script decode; **titles with entities: 7 → 0** |
| 5 | Broken archive thumbnails | Resolve WP attachment-page URLs → `media.json` / `wp-images/` stems; local file URLs preferred over photon; image audit in `parity/reports/drupal-image-audit/` |

## Image audit (801 paths, 2026-10-29)

| Metric | Before | After |
| --- | ---: | ---: |
| Broken `<img src>` / pages | 33 | 2 (page 404 only: `/home`, `/category/pediatrics`) |
| Broken on live WP too | 15 | **0** |
| WP-fixable image failures | 18 | **0** |

See `parity/reports/drupal-image-audit/README.md`.

## Entity decoding

Extended to body, excerpt, alt/caption, menu, taxonomy, meta. `audit_entity_leftovers.php` → **0** leftovers.

## Text parity (desktop, harness; after entity + excerpt tuning)

| Path | Text |
| --- | ---: |
| `/in-brief` | **92.1%** (linux ref; 15 vs 16 issue sections) |
| `/browse-issues` | **83.3%** (linux ref; issue grid copy) |
| `/category/cancer-research` | **100%** |
| `/category/digital-exclusives` | **40.8%** (Drupal archive row count vs ref HTML — content/view follow-up) |
| `/about-impact` | **96.8%** |
| `/2023/09/american-association-for-cancer-research` | **100%** |
| `/past-issues` | ~10% (ref pack stale; out of scope) |

Text harness: `PARITY_COMPARE_LINUX=1 PACK_COMPARE_PATHS=… node parity/scripts/compare-reference-pack.mjs`

Linux viewport pixel diffs remain dominated by phase4 chrome (~16%); out of scope per brief.

## Pre-push gate (fatal regression)

`df_impact_preprocess_views_view_fields()` must not treat Views `ResultRow` as an array when unsetting `#contextual_links` (see `cursor/vm-visual-parity-2b1b` / `f80dbbbd`).

```bash
drush -r web cr
cd parity && DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:template-samples:http
```

## Commands

```bash
drush -r web cr
drush -r web php:script modules/custom/df_migrate/scripts/decode_text_entities.php
DRUPAL_BASE_URL=http://127.0.0.1:8888 node parity/scripts/audit-drupal-images.mjs
```
