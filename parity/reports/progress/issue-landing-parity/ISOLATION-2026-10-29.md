# Issue landing isolation (PR #22 reproducibility)

**Branch:** `cursor/issue-landing-parity-185b` (PR #22)  
**Drupal:** shared VM DB, `drush updb` (includes `df_setup_update_10027` issue-page backfill)  
**WP baseline:** `parity/cache/wp-issue-pages/` (live landing HTML, not `issues.json` export stubs)

## Why STATUS once showed ≥98.9% but isolation saw ~0.85–0.95

| Factor | Effect |
| --- | --- |
| **Export stub baseline** | Content sweep defaults non-issue URLs to `issues.json` HTML (~9 chars). For issues, mis-prioritizing **frozen ref-pack** over **issue-cache** also skews text. Export vs cache Jaccard for `/fall-2025-issue` ≈ **0.02**. |
| **Cheerio vs Playwright extract** | `issue-live-text-sweep.mjs` uses Playwright `extractMainText` on full landing HTML. Legacy content sweep used Cheerio `extractContentMetrics` only → **0.91–0.95** on the same DB even when Drupal was fine. |
| **Unstable WP fixture load** | `page.setContent(cache)` without `url:` + lazy images caused flaky main roots → intermittent **0.50–0.68** on some seasons (not a Drupal regression). |
| **Wrong content root** | Playwright extractor did not prefer `article.issue-page` first; could match a partial `main` region during load. |
| **Card title entities** | PostX titles rendered `&lt;sup&gt;®&lt;/sup&gt;` literally (e.g. spring-2024 marathon story) → ~**98.89%** until titles decode like WP. |

## Fix (this branch)

1. **Shared Playwright scorer** — `lib/issue-playwright-text.mjs`; content sweep uses it for `template=issue` (with `drupalHtml` from HTTP, no concurrent `goto` races).
2. **Issue-cache before frozen** for WP HTML in content sweep.
3. **Hardened live sweep** — fixture base URL, lazy-image eager load, stabilize viewport, `article.issue-page` root.
4. **Theme** — `article_card_title` decode for issue grid cards.

## Repro commands

```bash
# PR #22 live sweep (15 comparable; skips late-fall-2022 archive error)
DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:issue-live:sweep

# Content sweep — issue paths only (same Playwright text metric)
CONTENT_SWEEP_PATHS="/fall-2023-issue,...,/winter-2025-issue" \
  DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:content:sweep

# One-path extractor diff (cache vs export stub)
node scripts/compare-issue-sweep-extract.mjs /fall-2025-issue
```

## Verified scores (2026-10-29, this VM)

| Scorer | Report | 15 comparable issues | min text |
| --- | --- | ---: | ---: |
| `issue-live-text-sweep.mjs` | `issue-live-sweep-1793272021647.json` | 15/15 ≥98% | **1.0** |
| `run-content-sweep.mjs` (issue Playwright text) | `sweep-1793273825991.json` | **15/15** ≥98% | **1.0** |

`/late-fall-2022-issue` remains **non-comparable** (WP `arch_err` / empty PostX in cache).
