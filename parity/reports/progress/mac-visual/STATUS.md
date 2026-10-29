# Mac visual parity — live status

**Branch:** `cursor/phase4-chrome-behavior-0b67` (integration PR **#5**).  
**PR #7** (`cursor/wp-assets-vendor-cdf8` → `main`) is **redundant** for this work: its commits are already on phase4; keep measuring and fixing here, not on #7.

**Updated:** 2026-10-28 (ET, Mac visual pass — fresh template-sample capture + fixes)

## URL parity — 9 failing paths (792/801 = 98.9%)

| Path | Drupal | WordPress | Cause |
|------|--------|-----------|--------|
| `/category/drug-development/page/2` | 404 | 301 → …/page/2/ | No Drupal **topic archive pager** for drug-development (pages 2–4) |
| `/category/drug-development/page/3` | 404 | 301 | Same |
| `/category/drug-development/page/4` | 404 | 301 | Same |
| `/category/pediatrics` | 404 | 301 | **Topic term / view route** not migrated (pediatrics category) |
| `/stories` | 404 | 404 | Legacy slug; **intentionally unmigrated** (both sides 404) |
| `/tag/digital-exclusives` | 404 | 301 | **Tag archives** not implemented in Drupal |
| `/tag/late-fall-2022` | 404 | 301 | Tag archive |
| `/tag/late-fall-2022/page/2` | 404 | 301 | Tag archive pager |
| `/topics` | 404 | 404 | Legacy hub; **both 404** |

Report: `parity/reports/url-parity/check-1793238507941.json`

## Mac reference pack (21 slugs, meta agent)

- **Pushed with branch:** full `parity/reference-pack/` (18 template-sample paths + head slugs), captured **2026-10-28** at 1440 / 768 / 375 via `npm run parity:capture-template-samples` + `stabilizeParityViewport()`.
- Re-run: `cd parity && npm run parity:capture-template-samples` (owner Mac, live WP).

## Template scores (DDEV vs fresh Mac pack, 2026-10-28 PM ET)

| Template | Structural | Viewport median (D / T / M) | Chrome median (D / T / M) | Notes |
|----------|------------|------------------------------:|----------------------------:|-------|
| **homepage** | pass | **32.6%** / 34.8% / 33.5% | 12.9% / 18.7% / 19.3% | Hero + PostX grid/order |
| **issue** | pass | **68.0%** / 66.1% / 62.9% | 11.6% / 14.9% / 19.4% | Banners backfilled; grid **order/set** vs WP PostX |
| **article** | pass | **31.7%** / 36.3% / 27.5% | 12.7% / 17.3% / 15.9% | Lead image crop + body blocks |
| **in_brief** | pass | **19.8%** / 13.2% / 15.3% | 12.7% / 17.8% / 16.6% | Best viewport template |
| **archive** | pass | **30.3%** / 56.0% / 36.8% | 12.9% / 16.8% / 15.6% | Was **46.5%** D before **category slug** harness fix |
| **landing_page** | pass | **34.4%** / 40.1% / 37.6% | 12.7% / 16.8% / 15.8% | Gutenberg block + image gaps |

## Fixes this pass (visual)

1. **`compare-reference-pack.mjs`:** `pathToPackSlug` for `/category/*` now matches capture slugs (`category_cancer-research`, not `category_cancer_research`) — archive scores trustworthy.
2. **`capture-stabilize.mjs`:** hide Drupal homepage **poster** when video hidden (matches WP pack video-only hero).
3. **`df_impact.theme`:** issue banner direct **1024×396** URLs (no `itok`); PostX cards use **i0.wp.com `fit=870×570`** via `media.json`; issue **banner backfill** from WP (`backfill_issue_page_from_wordpress.php`, 14 issues on DDEV).
4. **`parity:capture-template-samples`** npm script for reproducible Mac captures.

## Pixel diff autopsy (ranked causes)

| Area | ~Viewport | Top bands (desktop) | Primary causes (impact order) |
|------|----------:|---------------------|-------------------------------|
| **Chrome** | ~13% | y 0–148px header | Footer **badge PNG** bytes; mega menu **metrics**; logo AA — not width |
| **Homepage** | ~33% | y 0–110px then grids | **Item order/set** in latest-issue view; hero **blank vs poster** (stabilize aligned); card images now i0.wp.com |
| **Issue** | ~68% | y 0–110px header; banner+grid | **Article list order** (sticky/created vs WP PostX); promo cards; banner crop after backfill |
| **Archive** | ~30% D | listing body | **View row count** + card thumb crop (category compares fixed) |
| **Landing** | ~34% | full page | **Missing/partial Gutenberg** blocks + lead images on `/about`, `/about-impact` |
| **Article** | ~32% | hero + body | **1024×734 lead** derivative + drop-cap / block HTML |

Use `node parity/scripts/analyze-diff-bands.mjs parity/reports/progress/<template>/<slug>_desktop`.

## PR #9 merge + DDEV gates (full content)

| Gate | Result | Report / run |
|------|--------|----------------|
| **Merge** | `cc4d56a8` — PR #9 `cursor/meta-head-behavior-0751` into phase4 | `updatedb` OK; `cim` (browse_issues); `cr` OK |
| **`parity:head:refpack`** | **57.1%** (12/21 pack slugs); **19/21** Drupal reachable | `parity/reports/head-meta/compare-1793238200437.json` |
| **`parity:url:check`** (801 URLs) | **98.9%** (792/801) | `parity/reports/url-parity/check-1793238507941.json` |
| **`parity:behavior:smoke`** | **3/3** pass (`/homepage` D/T/M) | `parity/reports/behavior/2026-10-29T01-48-30-879Z/` |
| **`parity:smoke:baseline`** | **46/46** Playwright pass; ref-pack head self-tests **21/21** | run `2026-10-29T01-48-56-797Z` (pixel/text gates still fail — unchanged visual scope) |

**0% metaMatch root cause (post-merge):** merge conflict left `df_metatag_rewrite_to_production()` recursing on normal `https://…` URLs → PHP memory fatal → empty Drupal head in compare. Fixed by only stripping `/https://…` corruption (commit after merge).

Ref-pack **failures** (expected gaps, not harness): WP 404 slugs still in pack (`2022_03_in-brief…`, `digital-exclusives`); external canonical (Perer/Rosenberg → defycancer); category/archive **title** + simplified Drupal JSON-LD vs Yoast graph; some pages missing og:image on views.

## metaMatch (PR #6 + #9 — meta agent owns remaining head gaps)

| Item | Status |
|------|--------|
| Modules | `df_metatag`, `metatag_open_graph`, `metatag_twitter_cards` **enabled**; PR #9 adds `WordPressHeadOverrides`, topic slug lookup, canonical + JSON-LD in `metaMatches` |
| **Earlier 0%** | Unresolved metatag tokens + missing `<title>` (fixed on phase4 before #9) |
| **Post–#9 0% on DDEV** | Infinite recursion in `df_metatag_rewrite_to_production()` — **fixed** |
| **`parity:head:refpack` (authoritative on DDEV)** | **57.1%** (12/21), `compare-1793238200437.json` — up from **19%** pre-#9 on same 21-slug pack |
| Example head | `curl -s http://df-impact.ddev.site/2023/09/american-association-for-cancer-research` — title, canonical, og:* populated |

## Next (chrome + content — this agent)

1. **Issue/homepage grid order** — match WP PostX article order per issue (largest issue/homepage viewport gap).  
2. Chrome ≤5%: footer badge bytes + header micro-metrics.  
3. Landing **about / about-impact** block + image parity.  
4. Re-run `PARITY_TEMPLATE=* npm run parity:compare-template` after each fix.
