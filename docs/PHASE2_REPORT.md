# Phase 2 parity report

## Harness & acceptance criteria

- Owner thresholds documented in [ACCEPTANCE_CRITERIA.md](./ACCEPTANCE_CRITERIA.md) (supersedes Phase 1 pixel/text bars).
- Pass/fail: `parity/scripts/evaluate-thresholds.mjs` (pixel median ≤2% and p90 ≤5% per template × breakpoint; text ≥98%; desktop URL same-path HTTP 200; title/meta/OG match).
- **Smoke:** `npm run parity:smoke` (~25 content URLs). **Release gate:** `npm run parity` (full content manifest, ~900+ HTML URLs; media binaries excluded from Playwright until WP media export lands).

## Harness fixes (Phase 2)

| Fix | Why |
| --- | --- |
| `parity/lib/content-region.mjs` | Target WP `.entry-content` / Drupal `#main-content`; strip nav, sidebars, share blocks. |
| Separate Playwright pages for WP vs Drupal | Prevents SiteGround captcha navigation from breaking Drupal loads. |
| `wpCaptcha` flag + evaluate skip | Marks SiteGround challenge pages so scores are not trusted without `cookies.txt`. |
| URL check `maxRedirects: 0` | Same-path HTTP 200 only (no `/stories/` redirects). |
| Manifest filter in tests | Drops `wp-content/uploads`, feeds, PDFs from visual runs (still in manifest for future media parity). |

## Drupal URL / path work

- WordPress permalinks on articles/in-brief/issues via `WordpressPathHelper` + `df-migrate:sync-wordpress-paths` (purges legacy `/stories/` aliases).
- Home at **`/homepage`** HTTP 200 (`WordpressHomepageRouteSubscriber` disables front-page normalizer redirect).
- Issue nodes published (`moderation_state: published` + update `10012`).

## Baseline (Phase 1 sample, desktop)

From [PHASE1_GAP_REGISTER.md](./PHASE1_GAP_REGISTER.md) run `2026-10-28T15-00-23-422Z`:

| Metric | Phase 1 |
| --- | ---: |
| Pixel diff median | ~16.4% |
| Text similarity median | ~3.0% (mis-aligned regions) |

## Phase 2 smoke run (`2026-10-28T15-49-13-236Z`)

**Caveat:** Live WP returned SiteGround captcha (HTTP 202) on many requests without browser cookies — pixel/text/meta for those rows are **not** comparable. Re-run with owner `cookies.txt` for trustworthy WP-side metrics.

| Template × BP | Pixel median | Pixel p90 | Text median |
| --- | ---: | ---: | ---: |
| article::desktop | 15.5% | 32.6% | 47.3% |
| article::tablet | 19.6% | 31.3% | 47.3% |
| article::mobile | 33.8% | 36.3% | 94.3% |
| homepage::desktop | (see gap register) | | |
| issue::* | Issues were 403 until publish fix; re-run full harness | | |

**URL parity (Drupal, smoke sample):** Improved for dated article paths (HTTP 200 same path). Issues fixed after publish. `/home` correctly 404 (WP uses `/homepage/`). Media/upload paths deferred.

## Global chrome (remaining)

- Header logo scale and nav weight adjusted in `df_impact/css/layout.css`; pixel diffs still **critical** on homepage and nav-heavy pages (hero blocks, supplement row, footer spacing vs WP at 768/375).
- Secondary nav / Issues dropdown styling incomplete vs live WP.
- Meta/OG tags not yet aligned on Drupal (generator defaults, missing og:image on many nodes).

## Full manifest run

Run locally (Drupal on `:8888`, maintenance off):

```bash
cd parity && PARITY_BASELINE_RUN_ID=2026-10-28T15-00-23-422Z npm run parity
```

Results land in `parity/reports/runs/<runId>/`; gap register regenerates with before/after template×breakpoint table when baseline run id is provided.

## Owner asks

1. **WordPress DB export or admin export** for media — unlocks `/wp-content/uploads/…` URL parity and image pixel diffs (not blocking Phase 2 code paths).
2. **`cookies.txt`** (or SQL dump on SiteGround) so CI/agents can bypass captcha for stable WP baselines.
3. Confirm whether **`/stories`** and **`/home`** on live WP should 404/301 or need Drupal redirects (manifest still lists them; WP often redirects away).
