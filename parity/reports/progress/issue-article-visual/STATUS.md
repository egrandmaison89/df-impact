# Issue + article visual parity (pass-2 WP cache baseline)

Baseline: frozen HTML in `parity/cache/wp-issue-pages` and `parity/cache/wp-article-variants` (`PARITY_WP_SOURCE=cache`), **not** reference-pack totals (PR #25).

## Computed-style totals

| Run | `totalMismatches` | Jobs | Notes |
|-----|-------------------|------|--------|
| **before** `before-1793275744948` | **2918** | 33 | Pre fixture-wait / job-spec fixes |
| **after** `after-1793276157423` | **2728** | 33 | **−190** vs before |
| **pass3** `pass3-1793279970259` | **2475** | 33 | **−253** vs after; shared chrome + main width + video embed |

### Median comparable mismatches per template

| Template | Before | After | Pass3 |
|----------|--------|-------|-------|
| issue | 113 | 107 | **73** |
| article_hero | — | 90 | **70** |
| article_sidebar | — | 90 | **67** |
| article_no_hero | 94 | 76 | **72** |
| article_pull_quote | 72 | 57 | **67** |
| article_gallery | 77 | 58 | **68** |
| article_video_embed | 73 | 91 | **67** (< 73 ✓) |
| article_long_form | 75 | 75 | **67** |
| article_custom_blocks | 76 | 58 | **71** |

## Pass3 theme fixes (shared)

- **`drupal-overrides.css`:** `.wp-site-blocks` content-box width; main/footer **20px inset / max 1400px**; mega-menu link **min-width 117px** + box-sizing; desktop header **18px** vertical padding; hide empty `.wp-block-main-header`.
- **`components.css`:** YouTube/Vimeo **800px max-width**, **16:9** responsive wrapper + absolute iframe in `.entry-content` / `.article__body`.
- **`df_impact.theme`:** Local `wp-images` header logo when present; lead image prefers local file over Photon (video sample `imgNatural`).
- **`wp-fixture-html.mjs`:** Wait for header logo load in fixture ready.

## Shared chrome properties still differing (pass3 aggregate, all 33 jobs)

Most remaining noise is layout cascade (main/footer width), not isolated header CSS:

| Property (selector) | Jobs hit | Typical WP → Drupal |
|---------------------|----------|---------------------|
| `header :: height` / `rect.h` | 30 / 27 | 143px → 139px desktop; **269.5 → 190.8** tablet/mobile |
| `header :: rect.x` | 26 | 0 vs 20px (mobile/tablet inset) |
| `footer :: rect.x` / `rect.w` | 33 | 72/1296 vs 20/1400 desktop (main column breakout) |
| `main :: rect.h` / `height` | 33 | Content height (embeds, blocks, grid) |
| `.wp-site-blocks :: height` | 33 | Document height follows main/footer |

Mega-menu **width/style** deltas dropped after pass3 min-width/box-sizing; residual **4px** desktop header height is padding/stack, not menu item width.

## Article long_form — top property keys (pass3, all breakpoints)

1. `footer :: rect.x` / `rect.w` / `rect.y` — main column geometry (shared pass3 rule)
2. `.footer-dark :: rect.*` — full-bleed footer wrapper
3. `.wp-block-footer-widget-area-columns :: rect.*`
4. `main :: rect.h` / `height` — body copy length / floats
5. `.wp-site-blocks :: height`
6. `main :: rect.y` — header stack offset (~4px desktop)
7. Shared **header** height on tablet/mobile (~79px gap — mobile header block height)

Real content fixes in pass3 (not chrome): main width alignment + lead image URL; long_form median **75 → 67**.

## Text gates

- `parity:template-samples:http` **19/19** (`DRUPAL_BASE_URL=http://127.0.0.1:8888`)
- `parity:article-variants:compare` **16/16**
- Issue content sweep **15/15** comparable @ ≥0.98 (`/late-fall-2022-issue` = `wp_non_comparable` — WP cache/live archive shell)

## Commands

```bash
PARITY_WP_SOURCE=cache VISUAL_TAG=pass3 node parity/scripts/run-issue-article-visual-parity.mjs
node parity/scripts/aggregate-style-mismatches.mjs parity/reports/computed-style-diff/<runId>
node parity/scripts/aggregate-style-mismatches.mjs parity/reports/computed-style-diff/pass3-1793279970259
```
