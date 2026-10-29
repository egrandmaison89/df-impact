# VM visual parity — live status

**Branch:** `cursor/vm-visual-parity-2b1b` → PR targets `cursor/phase4-chrome-behavior-0b67` (PR **#5**).  
**Drupal:** `http://127.0.0.1:8888` (MariaDB `dfimpact`, host Drush).  
**Method:** `npm run parity:computed-style-diff` (live WP vs Drupal, desktop/tablet/mobile).

**Updated:** 2026-10-29 (integrator merge PR **#10** + **#11**)

---

## Integrator merges (this branch)

| Source | Branch | Result |
|--------|--------|--------|
| PR **#10** | `cursor/meta-head-behavior-0751` | Merged; conflicts resolved in `WpUploadFile.php`, `df_setup.install`, `drupal-overrides.css` (kept sticky mega-menu + `.wp-site-blocks` content-box) |
| PR **#11** | `cursor/templates-inbrief-archives-185b` | Clean merge (`theme.css` global library, in-brief twig, sync paths helper) |

### Gates after merges (VM)

| Gate | After PR #10 | After PR #11 + `/about` `/home` aliases |
|------|-------------|-------------------------------------------|
| `parity:head:refpack` | **21/21** metaPass (100%) | **21/21** (`compare-1793248825676.json`) |
| `parity:head:manifest` | 771/773 metaPass (pre-alias) | **773/773** (100%, `manifest-1793248913523.json`) |
| `parity:url:check` | 799/801 (404 on `/about`, `/home`) | **801/801** (100%, `check-1793248902907.json`) |
| `verify-wp-port-assets.sh` | — | **19/19 OK** |

**Note:** Applied legacy aliases via `df_setup_update_10022()` + `/home` alias; extended `df_sync_static_page_aliases()` so future `df-migrate:sync-wordpress-paths` keeps `/about` and `/home`.

---

## Computed-style-diff mismatch totals (desktop, live WP)

Report: `parity/reports/computed-style-diff/post-merge-pr11/summary.json`

| Template | Mismatch sum (all sample paths) | Primary deltas (when WP 200) |
|----------|--------------------------------:|------------------------------|
| **homepage** | **74** | Hero/grids vs short page when WP returns **202** captcha shell; chrome OK when both 200 |
| **issue** | **120** | PostX card **y/h** + image **w/h** on `fall-2026-issue`; issue banner **h1 x** on overlay |
| **article** | **69** | **main y +~149px** (WP breadcrumb band); lead/body typography |
| **in_brief** | **98** | Same **main y +~149px**; content height vs WP |
| **landing_page** | **133** | `/about` Gutenberg **columns/h2** layout; `browse-issues` grid height |
| **archive** | **188** | Archive row **thumb w** (`category/digital-exclusives`); `/digital-exclusives` **404** on Drupal (use `/category/digital-exclusives`) |

**Baseline (pre-content, `/` desktop):** **31** mismatches → **50** after full homepage content (migrations complete).

**Caveat:** Many runs show `wpStatus: 202` (SiteGround/captcha partial HTML). Compare rows with `wpStatus: 200` only for fix priority. Footer **h≈45px** on WP side is a captcha-page tell.

---

## Fix log

### Archive row thumbnails — **done**

`df_impact_archive_row_image_meta()` serves WP `_1450.jpg` URLs; `archive-row` class on topic archive twig.

### Computed-style harness — **done**

`parity/scripts/computed-style-diff.mjs`, `parity/config/parity-selectors.json`, npm script `parity:computed-style-diff`.

### Chrome header — **unchanged (good)**

Desktop `.desktop-header` **1440×148**, `.header-logo` **x=15** matches live WP. Open: gtranslate **x Δ≈119px**, mega menu **±2px** width.

---

## Next (visual list)

1. Issue PostX grid: card image rendered size + vertical rhythm (`ultp-block-item`).
2. Article / in-brief: WP Rank Math breadcrumb band (~149px) before `main`.
3. Landing `/about`: Gutenberg column blocks vs raw body.
4. Archive: row height + featured image box (re-measure after thumbnail URL fix).
5. `/digital-exclusives` same-path route (or drop from samples; canonical is `/category/digital-exclusives`).
6. Tablet/mobile chrome + gtranslate.

---

## Commands

```bash
cd drupal/web && ../vendor/bin/drush serve 127.0.0.1:8888
cd parity && npm ci && npx playwright install chromium
DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:computed-style-diff
DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:head:refpack
DRUPAL_BASE_URL=http://127.0.0.1:8888 URL_PARITY_DRUPAL_ONLY=1 npm run parity:url:check
PARITY_COMPARE_LINUX=1 DRUPAL_BASE_URL=http://127.0.0.1:8888 PARITY_TEMPLATE=issue npm run parity:compare-template
```
