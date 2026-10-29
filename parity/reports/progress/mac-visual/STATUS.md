# Mac visual parity — live status

**Branch:** `cursor/phase4-chrome-behavior-0b67` (PR **#5** only). **PR #7** redundant — close.

**Updated:** 2026-10-28 late ET (diff-driven chrome + issue/article/pediatrics)

---

## Score medians (Mac pack vs DDEV) — latest

| Template | Viewport D | Viewport T | Viewport M | Chrome D | Chrome T | Chrome M |
|----------|----------:|-----------:|-----------:|---------:|---------:|---------:|
| **homepage** | **31.9%** (was 32.6) | 33.7% | 32.4% | **4.9%** (was 12.9) | 16.1% | 17.7% |
| **issue** | **67.5%** (was 68.0) | 65.3% | 62.6% | **3.4%** (was 11.6) | 12.8% | 13.0% |
| **article** | **31.0%** (was 31.7) | 35.2% | 26.7% | **4.9%** | 14.6% | 15.6% |
| **in_brief** | **19.2%** | 13.2% | 15.3% | **5.0%** | 15.1% | 15.8% |
| **landing_page** | **33.7%** | 40.1% | 37.5% | **4.9%** | 14.0% | 15.4% |
| **archive** | **29.5%** | 56.0% | 36.8% | **5.0%** | 14.0% | 15.3% |

Command: `DRUPAL_BASE_URL=http://df-impact.ddev.site PARITY_TEMPLATE=<name> npm run parity:compare-template`

---

## Item log (overnight diff-driven pass)

### 1. Header chrome ≤5% desktop — **done (desktop)**

**Root cause (computed styles):** Drupal `.wp-site-blocks` used `border-box` width 1440px; WP uses `content-box` width 1296px + 72px padding → `.desktop-header` bled to **1584px**, logo **+144px** X.

**Fix:** `drupal-overrides.css` — `box-sizing: content-box` + `width: calc(100vw - 2*outer)`.

**Tooling:** `parity/scripts/compare-chrome-styles.mjs` (ref file:// vs Drupal, font audit).

**Stall:** Tablet/mobile chrome ~13–16% (mega menu typography / gtranslate select rasterization).

### 2. Issue template ~67% — **partial**

**Fixes:** PostX cards `fit=768×551` (not 870×570); photon-first card URLs; article **issue_promo** twig → PostX DOM (2 promo cards restored); grid `ultp-heading-filter` shell.

**Stall:** Viewport median ~**67.5%** unchanged — remaining diff is card raster parity (local wp-images vs i0.wp.com), promo excerpt copy, possible article set/order at 5 items. Needs media.json mapping expansion or vendored `-1024x734`-style card files.

### 3. Article lead 1024×734 — **partial**

**Fix:** `df_impact_wp_i0_derivative_url()` photon `resize=1024,734` preferred over image styles.

**Re-measure:** article D **31.0%** (was 31.7). Body typography / drop-cap HTML still open.

### 4. `/about`, `/about-impact` — **not started this pass** (~33.7% landing median).

### 5. Pediatrics + archive — **partial**

**Fix:** `df_setup_update_10026` creates **topics** term Pediatrics → `/category/pediatrics` **200** (was 404). Empty listing until articles tagged.

**Pager:** WP `/category/{slug}/page/N` already **200** (paginate subscriber).

### 6. Meta merge `b4e15df2` — **done**

Merged `origin/cursor/meta-head-behavior-0751` → `b9aa06d2`. Visual regression: homepage chrome D **~4.95%** (unchanged).

**`parity:head:refpack` (2026-10-29):** **61.9%** metaMatch (**13/21**), 19/21 Drupal reachable — report `parity/reports/head-meta/compare-1793244440548.json`. (Full 738-URL smoke metaMatch **~14.5%** uses a different baseline harness.)

---

## 738-URL run `2026-10-29T00-20-11-819Z`

| Metric | Value |
|--------|------:|
| Tests passed | 691 |
| urlParityPercent | **98.70%** |
| metaMatchPercent | **~14.5%** |
| failureCount | 753 |

Log: `/tmp/parity-full-738.log`

---

## Next

1. Merge meta `b4e15df2`; run `npm run parity:head:refpack`; report metaMatch.
2. Issue viewport: vendored card JPEGs or full photon on all grid images; compare promo excerpt text.
3. Landing `/about`, `/about-impact`: Gutenberg block parity from reference pack.
4. Mobile/tablet chrome ≤5%: mega-menu link computed styles vs `maxmegamenu_style.css`.
