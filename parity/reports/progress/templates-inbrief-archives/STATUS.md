# templates-inbrief-archives agent status

Branch: `cursor/templates-inbrief-archives-185b`  
Base: `cursor/phase4-chrome-behavior-0b67`  
PR: [#11](https://github.com/egrandmaison89/df-impact/pull/11)

## Owner manual QA (DDEV) — fixes landed

| # | Issue | Fix |
| --- | --- | --- |
| 1 | Pagination plain bulleted list | WP `wp-block-query-pagination` via `pager.html.twig` + `pager--wp-query.html.twig`, `css/wp-query-pagination.css`, all Views listing routes |
| 2 | In Brief band white-on-grey | `.stk-c3abd9d` navy background + white type (matches WP Stackable row) |
| 3 | Browse Issues grid/covers | Cover art from `field_cover_image`, 250px `figure.issue-cover`, 4-column flex grid CSS |
| 4 | `&#039;` in titles | `TextEntityHelper`, migrate `df_decode_plain_text`, drush/php script decode; **titles with entities: 7 → 0** |
| 5 | Broken archive thumbnails | Resolve WP attachment-page URLs → `media.json` / `wp-images/` stems; local file URLs preferred over photon; image audit in `parity/reports/drupal-image-audit/` |

## Text parity (desktop, harness; after entity + excerpt tuning)

| Path | Text |
| --- | ---: |
| `/in-brief` | ~92% |
| `/2023/09/american-association-for-cancer-research` | **100%** |
| `/2026/09/american-association-for-cancer-research-2` | **100%** |
| `/category/cancer-research` | **100%** (extract excludes Continue Reading) |
| `/about`, `/about-impact` | ~97% |
| `/issues` | ~94% |
| `/browse-issues` | ~86% |
| `/past-issues` | ~10% (ref pack is stale promo page, not issue grid) |

Linux viewport pixel diffs remain dominated by phase4 chrome (~16%); out of scope per brief.

## Commands

```bash
drush -r web cr
drush -r web php:script modules/custom/df_migrate/scripts/decode_text_entities.php
DRUPAL_BASE_URL=http://127.0.0.1:8888 node parity/scripts/audit-drupal-images.mjs
```
