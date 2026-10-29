# Issue + article visual parity (pass-4, rebased on PR #31)

Baseline: frozen HTML in `parity/cache/wp-issue-pages` and `parity/cache/wp-article-variants` (`PARITY_WP_SOURCE=cache`).

**Branch HEAD:** `d8701fa1` on `cursor/article-variants-parity-185b` (stacked on `#31` / `cursor/visual-chrome-pagination-d748` for inset ownership).

## Computed-style totals

| Run | `totalMismatches` | Jobs | Notes |
|-----|-------------------|------|--------|
| **after** `after-1793276157423` | **2728** | 33 | Pre pass-3 chrome |
| **pass4 (#31 base)** `pass4-1793300778127` | **2289** | 33 | Single main/footer inset from #31; mobile MENU toggle scoped; WP fixture mega collapse |

### Median comparable mismatches per template (after vs pass4 #31 base)

| Template | After | Pass4 | vs after |
|----------|-------|-------|----------|
| issue | 107 | **92** | ok |
| article_hero | 90 | **81** | ok |
| article_no_hero | 76 | **88** | reg |
| article_sidebar | 90 | **81** | ok |
| article_pull_quote | 57 | **42** | ok |
| article_gallery | 58 | **83** | **reg** |
| article_video_embed | 91 | **82** | ok |
| article_long_form | 75 | **42** | ok |
| article_custom_blocks | 58 | **42** | ok |

## Pass4 deltas vs old pass4 (pre-#31)

- **Chrome:** No duplicate `--wp--custom--spacing--outer`, 1400px main/footer block, or 768px `.wp-site-blocks` 20px override — #31 owns inset.
- **Article-only CSS:** mega menu desktop rules at **769px+**; `.mobile-header { display:block }`; `.mobile-header .mega-menu-toggle { display:block }` (duplicate `#mega-menu-wrap-*` id fix).
- **Harness:** `prepareWpFixtureHtml` collapses mobile mega in WP cache probes; header twig single `header.wp-block-template-part`.
- **Remaining gallery regression:** desktop/tablet `main` / `.wp-site-blocks` height (WP cache taller than Drupal on gallery sample) — body/content, not inset.

## Mobile header

Sample `article_custom_blocks` mobile: `.mobile-header` h **189.5** vs WP fixture **181** (Δ8.5px after MENU toggle fix; was ~78px short before toggle scoping).

## Text gates (this HEAD)

- `parity:template-samples:http` **19/19**
- `parity:article-variants:compare` **16/16**
- Issue content sweep (cache) **16/16** comparable @ ≥0.98
- `drush updb -y`: post_updates applied; `verify_df_setup_post_updates.php` **OK**; `verify_df_setup_schema.php` **WARN** (10027 marker)
- `parity:smoke`: fails on live WP captcha / meta (needs cookies baseline — unchanged)

## Commands

```bash
VISUAL_TAG=pass4 PARITY_WP_SOURCE=cache node parity/scripts/run-issue-article-visual-parity.mjs
node parity/scripts/aggregate-style-mismatches.mjs parity/reports/computed-style-diff/pass4-1793300778127
```
