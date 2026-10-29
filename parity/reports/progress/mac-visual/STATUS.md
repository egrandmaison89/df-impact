# Mac visual parity — live status

**Branch:** `cursor/phase4-chrome-behavior-0b67` (integration PR **#5**).  
**PR #7** (`cursor/wp-assets-vendor-cdf8` → `main`) is **redundant** for this work: its commits are already on phase4; keep measuring and fixing here, not on #7.

**Updated:** 2026-10-28 (ET)

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

## metaMatch (PR #6 + Mac fixes)

| Item | Status |
|------|--------|
| Modules | `df_metatag`, `metatag_open_graph`, `metatag_twitter_cards` **enabled** |
| **Root cause (was 0%)** | `df_metatag_metatags_alter()` rewrote **unresolved tokens** → canonical/og URLs like `https://danafarberimpact.org/http://df-impact.ddev.site/...`; theme lacked `<title>`; node description tokens used invalid `\|` fallback syntax |
| **Fixes** | URL rewrite moved to `hook_page_attachments_alter` after token replace; `<title>` in `html.html.twig`; `metatag.metatag_defaults.node` description tokens; OG image map via `media.json` for `wp-images/*` |
| **`parity:head:refpack`** | **19.0%** (4/21 pack pages), report `parity/reports/head-meta/compare-1793238045326.json` — remaining: view/page **empty og:image**, archive **title** strings, `/about` vs pack slug reachability |
| Example head | `curl -s http://df-impact.ddev.site/2022/11/couple-commits-2-million-to-fund-groundbreaking-clinical-trials \| grep -E 'title|og:'` — canonical, og:url, og:image, description OK |

## Next (chrome + content)

1. Chrome ≤5% pixel: fonts + footer badges  
2. Homepage/issue hero + PostX image pipeline (biggest viewport wins)  
3. metaMatch: view defaults og:image/title for archives and landing views  
4. Re-run `PARITY_TEMPLATE=* npm run parity:compare-template` after each fix; commit + push scores
