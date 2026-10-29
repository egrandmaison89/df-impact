# Issue + article visual parity (pass-2 WP cache baseline)

Baseline: frozen HTML in `parity/cache/wp-issue-pages` and `parity/cache/wp-article-variants` (`PARITY_WP_SOURCE=cache`), **not** reference-pack totals (PR #25).

## Computed-style totals

| Run | `totalMismatches` | Jobs | Notes |
|-----|-------------------|------|--------|
| **before** `before-1793275744948` | **2918** | 33 (hero/sidebar deduped by path — fixed in harness) | Pre fixture-wait / banner / job-spec fixes |
| **after** `after-1793276157423` | **2728** | 33 | `-190`; hero + sidebar jobs included |

### Median comparable mismatches per template

| Template | Before median | After median |
|----------|---------------|--------------|
| issue | 113 | 107 |
| article_hero | — | 90 |
| article_sidebar | — | 90 |
| article_no_hero | 94 | 76 |
| article_pull_quote | 72 | 57 |
| article_gallery | 77 | 58 |
| article_video_embed | 73 | 91 |
| article_long_form | 75 | 75 |
| article_custom_blocks | 76 | 58 |

## Top real mismatch themes (content-focused)

1. **Harness (before):** WP `setContent` measured PostX before grid CSS + images → false full-width cards and `.wp-site-blocks` height noise. **Fixed:** `waitForWpFixtureReady`, `PARITY_JOB_SPECS`, `waitUntil: load`.
2. **Issue banner:** WP `aspect-ratio: unset` + scaled asset; Drupal used 1024×396 Photon → cover height drift. **Fixed:** twig + theme CSS + local full banner URL when file exists.
3. **Issue grid (after fall-2025 desktop):** First card ~628×451px both sides; remaining deltas mostly `main` height (~70px), mega-menu chrome styles, footer y.
4. **Articles:** Entry typography, columns, embeds; variant medians down except video_embed (iframe sizing — follow-up).

## Region-band analyzer

Issue landings: 3 samples × desktop/tablet/mobile captures under `progress/issue-article-visual/<runId>/` + `mac-visual/qa-evidence/*-{before,after}`.

## Commands

```bash
VISUAL_TAG=before node parity/scripts/run-issue-article-visual-parity.mjs
VISUAL_TAG=after node parity/scripts/run-issue-article-visual-parity.mjs
node parity/scripts/aggregate-style-mismatches.mjs parity/reports/computed-style-diff/<runId>
```
