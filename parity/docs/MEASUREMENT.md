# Viewport parity measurement (Mac pack + baseline)

## Capture stabilization (2026-10-29)

Before viewport screenshots, both harnesses call `stabilizeParityViewport()` (`parity/lib/capture-stabilize.mjs`):

1. **Hero video** — pause, seek to 0, hide `<video>` so the **poster / cover image** is what the screenshot sees (matches frozen Mac `viewport.png` better than random frames).
2. **Lazy load** — scroll full page, promote `data-src` → `src`, wait for `img.complete`.
3. **Motion** — `preparePage()` disables CSS animations/transitions (existing).

Used in: `parity/tests/parity.spec.ts`, `parity/scripts/compare-reference-pack.mjs` (Mac pack mode).

## Homepage `/` diff autopsy (pre-stabilization)

Side-by-side: `parity/reports/progress/homepage/root_desktop/{wp-viewport-mac,drupal-viewport,diff-viewport-mac}.png` (1440×900).

**Top 3 diff regions** (pixelmatch ~61% overall; ~32% raw RGB proximity at 10px grid):

| Rank | Region (y on viewport) | Cause |
| --- | --- | --- |
| 1 | **Hero cover** (~150–400px) | Live `<video>` frame vs Mac pack still; unrelated pixels every run. |
| 2 | **PostX “Explore” + card grids** (~400–700px) | Card images: WP `i0.wp.com?resize=…` vs Drupal full local files (different scale/crop/sharpening). |
| 3 | **Digital exclusives row** (~650–900px) | Same image pipeline mismatch + possible story set/order drift vs pack capture date. |

Header/footer **chrome** medians were ~13% desktop with element screenshots; full viewport is dominated by hero + grids, not chrome inset.

## Template sampling

Use `parity/config/template-samples.json` — 3–5 paths per template (new/old/special). Run:

```bash
PACK_COMPARE_PATHS=$(node -e "console.log(require('./config/template-samples.json').article.paths.join(','))") \
PROGRESS_COMPONENT=article \
DRUPAL_BASE_URL=http://df-impact.ddev.site \
npm run parity:compare-ref-pack
```

Full **738-URL** run is for outlier clusters only; fix at template/filter level.

## Pre-push guard: `parity:smoke:200` (required for all agents)

Before every push to visual/meta/template branches (and before opening PRs into **`cursor/phase4-chrome-behavior-0b67`** / PR **#5**), run:

```bash
cd drupal/web && ../vendor/bin/drush serve 127.0.0.1:8888   # or DDEV
cd parity && npm ci
DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:smoke:200
```

**What it checks (fast, Drupal-only):**

1. **HTTP** — every path in `parity/config/template-samples.json` plus the manifest **sample inventory** (`loadUrls()` with `PARITY_SAMPLE=1`, same picks as parity smoke runs) must not return **5xx** or a Drupal fatal HTML shell.
2. **Watchdog** — no new **`php`** log rows (severity ≤ Error) after the run starts.

Report: `parity/reports/smoke-200/smoke-<timestamp>.json`. Exit **0** = OK, **1** = failing path(s) or watchdog, **2** = Drupal unreachable (`SMOKE_DRUPAL_REQUIRED=1`, default).

**CI:** `.github/workflows/parity-smoke-200.yml` runs on PRs to `cursor/phase4-chrome-behavior-0b67` when `DRUPAL_SMOKE_BASE_URL` (repo variable) or `http://127.0.0.1:8888` is reachable; otherwise it warns and skips (hosted runners have no DB by default).

**Typical failure:** theme preprocess fatals (e.g. treating a Views `ResultRow` as an array) → site-wide **500**; caught by homepage + template samples in one pass.
