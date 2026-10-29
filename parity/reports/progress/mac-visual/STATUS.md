# Mac visual parity — live status

**Branch:** `cursor/phase4-chrome-behavior-0b67` (integration PR **#5**).  
**PR #7** (`cursor/wp-assets-vendor-cdf8` → `main`) is **redundant** for this work: its commits are already on phase4; keep measuring and fixing here, not on #7.

**Updated:** 2026-10-28 (ET, post–PR #9 DDEV verify)

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

## Mac reference pack (template-samples)

- Re-captured **all 20 paths** in `parity/config/template-samples.json` at **1440 / 768 / 375** via `capture-reference-pack.mjs` + `stabilizeParityViewport()` (live WP, no captcha).
- New/ refreshed slugs include: `about`, `about-impact`, `spring-2026-issue`, `category_access-and-equity`, `category_digital-exclusives`, in-brief singles, etc.
- **WP 404** (archive error pages, not Drupal): `/2022/03/in-brief-spring-2022-roundup`, `/digital-exclusives` — removed from `template-samples.json` (use `/category/digital-exclusives` and in-brief URLs that return 200 on WP).

## Template scores (all samples, DDEV, fresh Mac pack)

| Template | Structural | Viewport median (D / T / M) | Chrome median (D / T / M) |
|----------|------------|------------------------------:|----------------------------:|
| **homepage** | pass | **32.6%** / 34.8% / 33.5% | 13.0% / 18.7% / 19.2% |
| **issue** | pass | **68.0%** / 66.1% / 62.9% | 11.8% / 14.8% / 19.2% |
| **article** | pass | **31.7%** / 36.3% / 27.5% | 12.7% / 17.3% / 15.9% |
| **in_brief** | pass | **19.8%** / 13.2% / 15.3% | 12.7% / 17.8% / 16.6% |
| **archive** | pass | **46.5%** / 68.3% / 42.2% | 12.9% / 16.8% / 15.5% |
| **landing_page** | pass | **34.4%** / 40.1% / 37.6% | 12.8% / 16.8% / 15.8% |

Structural chrome rects pass; remaining gap is **content/rendering**, not layout width.

## Pixel diff autopsy (top regions — not AA noise)

From fresh compares + `parity/scripts/analyze-diff-bands.mjs` / `parity/docs/MEASUREMENT.md`:

| Area | ~Viewport diff | Dominant regions | Likely cause (fix order) |
|------|---------------:|------------------|-------------------------|
| **Chrome** (~13% D) | Header/footer strips | Mega menu **font weight/size** vs WP; footer **badge PNG** bytes; minor logo AA | Fonts loaded (verify `document.fonts` / WP `@font-face` in theme); swap footer assets to pack bytes |
| **Homepage** (~33% D) | Hero ~150–450px; grids below | **Hero poster** vs stabilized video frame; PostX cards **i0.wp.com resize** vs local full files; story **set/order** vs capture date | Hero poster URL parity; card `src`/`srcset` to WP resize params or vendored crops |
| **Landing** (~34% D) | Full page | About/Impact **Gutenberg blocks** partial vs WP; missing **lead images**; font stack | Content parity + WP image URLs on blocks |
| **Archive** (~47–68% D) | Listing grids | **Fewer/different nodes** in views vs WP listing at capture time; card thumbs **crop/size** | View row counts + featured image derivatives |
| **Issue** (~68% D) | Banner + grids | Issue **banner/cover** sizing; PostX grids same as homepage pipeline | `wp_issue_banner_1024x396` + card 870×570 pipeline |

Do **not** treat as anti-aliasing until a WP-vs-WP rerun noise floor is measured.

## metaMatch (PR #6 + #9 + Mac fixes)

| Item | Status |
|------|--------|
| Modules | `df_metatag`, `metatag_open_graph`, `metatag_twitter_cards` **enabled**; PR #9 adds `WordPressHeadOverrides`, topic slug lookup, canonical + JSON-LD in `metaMatches` |
| **Earlier 0%** | Unresolved metatag tokens + missing `<title>` (fixed on phase4 before #9) |
| **Post–#9 0% on DDEV** | Infinite recursion in `df_metatag_rewrite_to_production()` — **fixed** |
| **`parity:head:refpack` (authoritative on DDEV)** | **57.1%** (12/21), `compare-1793238200437.json` — up from **19%** pre-#9 on same 21-slug pack |
| Example head | `curl -s http://df-impact.ddev.site/2023/09/american-association-for-cancer-research` — title, canonical, og:* populated |

## Next (chrome + content)

1. Chrome ≤5% pixel: fonts + footer badges  
2. Homepage/issue hero + PostX image pipeline (biggest viewport wins)  
3. metaMatch: view defaults og:image/title for archives and landing views  
4. Re-run `PARITY_TEMPLATE=* npm run parity:compare-template` after each fix; commit + push scores
