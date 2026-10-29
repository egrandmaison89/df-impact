# Mac visual parity — live status

**Branch:** `cursor/phase4-chrome-behavior-0b67` (PR **#5** only). **PR #7** redundant — close.

**Updated:** 2026-10-28 11:45 PM ET (PR **#10** merge + DDEV gates)

---

## DDEV parity gates (post PR #10 merge, `http://df-impact.ddev.site`)

| Gate | Result | Report |
|------|--------|--------|
| **`parity:head:refpack`** | **21/21** metaPass (100%), 19/21 Drupal reachable | `parity/reports/head-meta/compare-1793244596979.json` |
| **`parity:head:manifest`** | **773/773** metaPass (100%) of 773 with expected (801 manifest URLs) | `parity/reports/head-meta/manifest-1793244662018.json` |
| **`parity:url:check`** (`URL_PARITY_CONCURRENCY=1`) | **801/801** pass (100%) | `parity/reports/url-parity/check-1793244664143.json` |

Pipeline: `node parity/scripts/build-refpack-head-overrides.mjs` → `ddev drush updb -y && ddev drush cr` → npm scripts above.

**Update hooks:** `df_setup_update_10025` PostX sort fields/backfill; `df_setup_update_10026` Pediatrics topic (slug check + optional Patient Care parent). No pending `updb` on DDEV after merge.

---

## Score medians (Mac pack vs DDEV) — post PR #10 merge

| Template | Viewport D | Viewport T | Viewport M | Chrome D | Chrome T | Chrome M |
|----------|----------:|-----------:|-----------:|---------:|---------:|---------:|
| **homepage** | **33.0%** | 33.7% | 32.4% | **5.1%** | 16.1% | 17.7% |
| **issue** | **67.8%** | 65.3% | 62.6% | **3.6%** | 12.8% | 13.0% |
| **article** | **32.0%** | 35.2% | 26.7% | **5.0%** | 14.6% | 15.6% |
| **in_brief** | **20.2%** | 12.3% | 14.9% | **~5%** | 15.1% | 15.8% |
| **landing_page** | **34.8%** (`/about` D) | 39.1% | 36.0% | **5.1%** | 14.0% | 15.4% |
| **archive** | **30.5%** | 54.8% | 35.6% | **~5%** | 14.0% | 15.3% |

Desktop chrome **≤~5.1%** on homepage, issue, article, landing (`/about`) after merge; PR #10 `.alignfull` negative margins omitted (they fight content-box and restore +144px logo offset).

Command: `DRUPAL_BASE_URL=http://df-impact.ddev.site PARITY_TEMPLATE=<name> npm run parity:compare-template`

---

## Item log

### PR #10 merge (`cursor/meta-head-behavior-0751`) — **done**

Merged into PR #5 branch (`06f8ac94`). Brings meta head overrides, sticky header + mega-menu hover behavior, `WpUploadFile` wp-images copy into uploads path, Pediatrics + PostX update hooks.

**CSS note:** PR #10 `.alignfull` negative margins are **not** combined with content-box chrome fix (restores 1584px bleed). Sticky/hover rules kept.

### 1. Header chrome ≤5% desktop — **done (homepage / issue / article)**

Content-box `.wp-site-blocks` + sticky header from PR #10.

### 2. Issue template ~67% — **partial** (viewport **67.8%**)

Card raster / promo excerpt / grid order still open.

### 3. Article lead 1024×734 — **partial** (viewport **32.0%**)

Body typography / drop-cap still open.

### 4. `/about`, `/about-impact` — **in progress** (~34.8% landing median)

### 5. Pediatrics + archive — **partial**

`10026` on merge branch; `/category/pediatrics` **200**.

### 6. Meta head (supersedes `b4e15df2` only) — **done**

See DDEV gates table above.

---

## Next

1. Landing **`/about`**, **`/about-impact`**: Gutenberg block parity from reference pack.
2. Issue viewport: vendored card JPEGs or photon on all grid images; promo excerpt text.
3. Re-run chrome on in_brief / landing / archive after any global CSS change.
4. Mobile/tablet chrome ≤5%.
