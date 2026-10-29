# Issue + article visual parity (pass-4 WP cache baseline)

Baseline: frozen HTML in `parity/cache/wp-issue-pages` and `parity/cache/wp-article-variants` (`PARITY_WP_SOURCE=cache`), **not** reference-pack totals.

## Computed-style totals

| Run | `totalMismatches` | Jobs | Notes |
|-----|-------------------|------|--------|
| **after** `after-1793276157423` | **2728** | 33 | Pre pass-3 chrome |
| **pass3** `pass3-1793279970259` | **2475** | 33 | Global main/footer + content-box (regressed pull_quote / gallery / custom_blocks medians) |
| **pass4** `pass4-1793290601355` | **2120** | 33 | Issue-scoped main/footer, mobile header 100vw bleed, header twig parity, article WP cache committed |

### Median comparable mismatches per template (after vs pass4)

| Template | After | Pass4 | vs after |
|----------|-------|-------|----------|
| issue | 107 | **80** | ok |
| article_hero | 90 | **54** | ok |
| article_no_hero | 76 | **62** | ok |
| article_sidebar | 90 | **54** | ok |
| article_pull_quote | 57 | **54** | ok |
| article_gallery | 58 | **75** | reg (pass3 scoped chrome removed global main breakout; residual header stack + gallery body height) |
| article_video_embed | 91 | **54** | ok |
| article_long_form | 75 | **73** | ok |
| article_custom_blocks | 58 | **73** | reg (tablet/mobile chrome + block layout; desktop main column unchanged vs pass3) |

## Pass4 root cause (three pass3 regressions)

| Regression | Pass3 cause | Pass4 fix |
|------------|-------------|-----------|
| **pull_quote** (+10 desktop) | Global `> main` / footer 1400px breakout + fixture refresh | Issue-only breakout; **pull_quote median 54 ≤ 57** |
| **gallery** (+10 median) | Same global main/footer on issue pages bled into comparable footer/main rects on gallery desktop | Scoped `:has(main .issue-page)`; gallery desktop down vs pass3 107 but mobile header/menu stack still ~75 median |
| **custom_blocks** (+13 tablet) | Mega-menu `@media (min-width: 768px)` + mobile `.wp-site-blocks` inset | Mega rules at **769px+**; mobile header **100vw** alignfull (not −20px bleed) |

Shared chrome kept from pass3: `.wp-site-blocks` **content-box** width, video embed CSS, issue-only main/footer **1400px** column on desktop.

## Mobile / tablet header

- **Twig:** single `header.wp-block-template-part` (removed empty `.wp-block-main-header` shell).
- **CSS:** `.mobile-header.alignfull` uses **100vw** + `calc(50% - 50vw)` margins (WP blockmag bleed); collapsed mega menu via Max Mega Menu + `display:none` when not `.mega-menu-open`.

## Text gates (pass4 branch)

- `parity:template-samples:http` **19/19** (`DRUPAL_BASE_URL=http://127.0.0.1:8888`)
- `parity:article-variants:compare` **16/16**
- Issue content sweep **15/15** comparable @ ≥0.98 (`PARITY_ISSUE_WP_CACHE_ONLY=1`)

## Commands

```bash
VISUAL_TAG=pass4 PARITY_WP_SOURCE=cache node parity/scripts/run-issue-article-visual-parity.mjs
node parity/scripts/aggregate-style-mismatches.mjs parity/reports/computed-style-diff/pass4-1793290601355
```
