# VM visual parity — live status

**Branch:** `cursor/vm-visual-parity-2b1b` → PR targets `cursor/phase4-chrome-behavior-0b67` (PR **#5**).  
**Drupal:** `http://127.0.0.1:8888` (MariaDB `dfimpact`, host Drush).  
**Method:** `npm run parity:computed-style-diff` (live WP vs Drupal, desktop/tablet/mobile).

**Updated:** 2026-10-29 (smoke-200 guard, refpack about/archive, chrome tablet/mobile)

---

## PR #12 diff autopsy vs `cursor/phase4-chrome-behavior-0b67`

GitHub showed ~**256k deletions** because an earlier merge of PR **#10** dropped the tracked **`drupal/web/sites/default/files/wp-images/**`** tree (images were moved aside locally during conflict resolution; the meta branch carried a smaller subset).

**Verified intact (0 deletions vs phase4):** `parity/reference-pack/`, Mac reference PNGs, `drupal/web/modules/custom/df_migrate/data/`, `config/sync`, migration JSON under `drupal/migration-data/`, vendored `wp-assets` (minus accidental VM `blob_*` commits, since removed).

**Remediation (commits on this branch):** restored full phase4 `wp-images`; removed VM `computed-style-diff` / url-parity JSON from the index; gitignored `parity/reports/computed-style-diff/`; restored `playwright-results.json` + head-meta manifest snapshot from phase4.

**After fix:** `git diff --shortstat origin/cursor/phase4-chrome-behavior-0b67...HEAD` ≈ **614 files, +35,620 / −316**; **0 path deletions** vs phase4.

---

## Integrator merges (this branch)

| Source | Branch | Result |
|--------|--------|--------|
| PR **#10** | `cursor/meta-head-behavior-0751` | Merged; conflicts resolved in `WpUploadFile.php`, `df_setup.install`, `drupal-overrides.css` (kept sticky mega-menu + `.wp-site-blocks` content-box) |
| PR **#11** | `cursor/templates-inbrief-archives-185b` | Re-merged @ `7de8f98b` — `archive_listings` library, slug→wp-images fallback, archive row classes |
| PR **#10** | `cursor/meta-head-behavior-0751` | Re-merged @ `380a9429` — behavior harness + sticky header CSS (after prior partial merge) |

### Gates (frozen refpack — no live WP for refpack meta)

| Gate | After bad merge (VM) | Root cause | After fix (`f80dbbbd`) |
|------|---------------------|------------|-------------------------|
| `parity:head:refpack` | **11/21** (`compare-1793251428009`) | **Drupal 500** on most paths: `unset($variables['row']['#contextual_links'])` on `ResultRow` in `df_impact_preprocess_views_view_fields()` | **21/21** (`compare-1793251907739.json`, `HEAD_REFPACK_CHECK_WP=0`) |
| `parity:url:check` | **764/801** | Same **500** cascade (non-200 Drupal) | **801/801** (`check-1793251875412.json`, `URL_PARITY_DRUPAL_ONLY=1`) |
| `parity:head:manifest` | (not re-run when red) | — | **773/773** (`manifest-1793251910500.json`) |

**Refpack failures when red (Drupal status + meta delta):**

| Path | drStatus | Issue |
|------|----------|--------|
| `/` … most refpack paths | **500** | PHP fatal in views preprocess (above) |
| `/digital-exclusives` | **200** | Stale refpack baseline was WP **404** meta; Drupal serves category archive via `WordpressLegacyPathSubscriber` — fixed by mapping slug `digital-exclusives` → `category_digital-exclusives` frozen HTML in `compare-head-meta.mjs` |
| `/2022/03/in-brief-spring-2022-roundup` | **404** | Matches frozen WP 404 baseline; **metaMatch OK** (counts toward 21/21, not in `drupalReachable`) |

**Header scroll CSS:** Removed `.desktop-header.scrolled` / `.impact-header.scrolled` width overrides from `drupal-overrides.css` (reintroduced during meta merge). Shadow only on `.header--scrolled`; logo shrink stays on `.header-logo.scrolled` via `wp-chrome-behaviors.js` (meta **adde8d85** intent).

### Pre-push guard — **`parity:smoke:200`** (required)

`npm run parity:smoke:200` — **39 paths** (all `template-samples.json` + manifest sample inventory), **0** HTTP fails after `9acb109d`. Documented in `parity/docs/MEASUREMENT.md`; CI: `.github/workflows/parity-smoke-200.yml` on PRs to `cursor/phase4-chrome-behavior-0b67`.

**Note:** Applied legacy aliases via `df_setup_update_10022()` + `/home` alias; extended `df_sync_static_page_aliases()` so future `df-migrate:sync-wordpress-paths` keeps `/about` and `/home`.

---

## Computed-style-diff mismatch totals (desktop, live WP)

Report: `parity/reports/computed-style-diff/post-merge-pr11/summary.json`

| Template | Mismatch sum (all sample paths) | Primary deltas (when WP 200) |
|----------|--------------------------------:|------------------------------|
| **homepage** | **74** | Hero/grids vs short page when WP returns **202** captcha shell; chrome OK when both 200 |
| **issue** | **120** | PostX card **y/h** + image **w/h** on `fall-2026-issue`; issue banner **h1 x** on overlay |
| **article** | **69** → **40** (couple, WP 200) | **main y Δ≈6px**; **main h Δ≈312px** still (`post-clearfix-main`) — body/related block height vs WP, not float collapse |
| **in_brief** | **98** | Same title/`<main>` split + spacer (re-measure pending) |
| **landing_page** | refpack **`landing-about`** | `/about` desktop viewport Linux **16.7%**, Mac **34.8%**; text sim **96.2%**; chrome **Δx=72** (`.wp-site-blocks` padding) |
| **archive** | refpack **`archive-refpack`** (3 categories × 3 BP) | Desktop viewport Mac median **30.3%**; archive row thumb CSS added; DE text sim **41%** (listing copy drift) |
| **homepage tablet/mobile** | live WP **`post-tablet-home`** + refpack medians | Tablet **87** mismatches; refpack chrome median Linux **~17%** tablet / **~21%** mobile after **desktop/mobile header toggle** in `drupal-overrides.css` |

**Baseline (pre-content, `/` desktop):** **31** mismatches → **50** after full homepage content (migrations complete).

**Caveat:** Many runs show `wpStatus: 202` (SiteGround/captcha partial HTML). Compare rows with `wpStatus: 200` only for fix priority. Footer **h≈45px** on WP side is a captcha-page tell.

---

## Fix log

### Archive row thumbnails — **done**

`df_impact_archive_row_image_meta()` serves WP `_1450.jpg` URLs; `archive-row` class on topic archive twig.

### Computed-style harness — **done**

`parity/scripts/computed-style-diff.mjs`, `parity/config/parity-selectors.json`, npm script `parity:computed-style-diff`. WP loads retry with backoff on **202** / captcha HTML / missing header / stub footer; **`wpComparable`** runs feed **`byTemplate.medianMismatchComparable`** (non-comparable samples excluded from medians).

### Chrome header — **unchanged (good)**

Desktop `.desktop-header` **1440×148**, `.header-logo` **x=15** matches live WP. Open: gtranslate **x Δ≈119px**, mega menu **±2px** width.

---

## Article template (owner QA — 2026-10-29)

| Item | Status |
|------|--------|
| Remove Drupal breadcrumb / tabs / contextual pencils on public pages | **done** — breadcrumb block off; `preprocess_breadcrumb` empty; hide `.contextual` + tabs in `drupal-overrides.css`; drop `title_prefix` on article/in-brief |
| **More stories** Jetpack grid + 350×200 images | **done** — `wp_jetpack_related_posts.json` from migration `jetpack-related-posts` (439 rows, 317 with 3 refs); `drush df-migrate:backfill-jetpack-related`; **in_brief** targets allowed on `field_related_articles`. Verified titles: couple / rasmussen / v-foundation vs export |
| **main y ~149px** | **partial** — `page--node--article|in-brief.html.twig` title above `<main>`; 46px spacer; Charlie L **main y** no longer in top deltas (`post-main-spacer46`) |
| Category links (`wp-block-post-terms`) | **done** — comma separators + `/category/{slug}`; removed pill `.article__tag` styling |

## Next (visual list)

1. **main h** / article body height vs WP (Charlie L Δ~684px on `main` height — content or selector scope).
2. Issue PostX grid: card image rendered size + vertical rhythm (`ultp-block-item`).
3. Full desktop computed-style pass after WP egress stable (many samples `wpStatus: 0/202` from VM timeouts).
4. Archive: re-run `PARITY_TEMPLATE=archive` after templates merge.
5. Tablet/mobile chrome + gtranslate (behavior harness landed from meta branch).

---

## Commands

```bash
cd drupal/web && ../vendor/bin/drush serve 127.0.0.1:8888
cd parity && npm ci && npx playwright install chromium
DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:smoke:200
DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:computed-style-diff
DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:head:refpack
DRUPAL_BASE_URL=http://127.0.0.1:8888 URL_PARITY_DRUPAL_ONLY=1 npm run parity:url:check
PARITY_COMPARE_LINUX=1 DRUPAL_BASE_URL=http://127.0.0.1:8888 PARITY_TEMPLATE=issue npm run parity:compare-template
```
