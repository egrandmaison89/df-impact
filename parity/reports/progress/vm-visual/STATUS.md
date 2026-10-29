# VM visual parity — live status

**Branch:** `cursor/vm-visual-parity-2b1b` (PR targets `cursor/phase4-chrome-behavior-0b67` / PR **#5**).  
**Drupal:** `http://127.0.0.1:8888` (MariaDB `dfimpact`, host Drush).  
**Method:** `npm run parity:computed-style-diff` — live WP vs Drupal computed styles + boxes (1440 / 768 / 375).

**Updated:** 2026-10-29 (VM bootstrap + harness)

---

## Harness

| Artifact | Path |
| --- | --- |
| Script | `parity/scripts/computed-style-diff.mjs` |
| Selectors | `parity/config/parity-selectors.json` |
| Samples | `parity/config/template-samples.json` |
| Reports | `parity/reports/computed-style-diff/<run-id>/summary.json` |

```bash
cd parity
DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:computed-style-diff
PARITY_TEMPLATE=issue PARITY_BP=desktop npm run parity:computed-style-diff
```

Secondary pixel signal: `PARITY_COMPARE_LINUX=1 DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:compare-template`

---

## Mismatch counts (computed-style-diff)

| Template | Before (notes) | After (notes) |
|----------|----------------:|--------------:|
| **homepage** | **31** `/` desktop (empty DB — main/footer height) | _pending full migrations_ |
| **issue** | — | — |
| **article** | — | — |
| **in_brief** | — | — |
| **landing_page** | — | — |
| **archive** | — | — |

**Chrome-only (`/` desktop, pre-content):** header `.desktop-header` **1440×148**, `.header-logo` **x=15** matches live WP (alignfull/content-box fix already on base branch). Remaining chrome nits: gtranslate **x Δ≈119px**, mega menu width **±2px**.

---

## Fix log

### 1. Archive row thumbnails — **done (code)**

WP archive rows use `attachment-post-thumbnail` `_1450.jpg` URLs, not PostX `fit=768×551`. Added `df_impact_archive_row_image_meta()` + `archive-row` class on topic archive twig.

### 2. Computed-style diff tooling — **done**

Live WP vs Drupal Playwright harness committed; `parity:computed-style-diff` npm script.

### 3. Migrations + hero backfill — **in progress**

VM bootstrap from config + JSON; `df_media_images` 853/853; batch importing `df_articles` / `df_in_brief`.

---

## Next

1. Finish migrations, `drush df-migrate:sync-wordpress-paths`, hero backfill, `verify-wp-port-assets.sh`.
2. Re-run full computed-style-diff per template; fix largest box/style deltas (issue cards, article lead 1024×734, `/about` blocks, pediatrics term listing).
3. Tablet/mobile chrome gtranslate / mega menu typography.
