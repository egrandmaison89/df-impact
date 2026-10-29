# Mac visual parity — live status

**Branch:** `cursor/phase4-chrome-behavior-0b67` (integration PR **#5** only).  
**PR #7** (`cursor/wp-assets-vendor-cdf8` → `main`) is **redundant** — same work lives on phase4; **safe to close #7**.

**Updated:** 2026-10-28 late ET (PostX order, chrome/archive fixes, full template re-measure)

---

## Overnight checklist (agent)

| # | Item | Status | Notes |
|---|------|--------|-------|
| 1 | PostX / homepage grid order | **Done** | `field_postx_sort_weight`, `field_homepage_sort_weight`, Views sorts, backfill script, DDEV `parity` bind mount |
| 2 | Chrome ≤5% | **In progress** | Footer badge PNGs + WP filenames; font preload in compare; header still ~13% D (mega menu / metrics) |
| 3 | Landing `/about`, `/about-impact` | **Open** | ~34% D viewport; Gutenberg blocks + lead images |
| 4 | Archive pager / rows | **Partial** | WP `/category/{slug}/page/N` → 200 on DDEV; pediatrics term still missing |
| 5 | Article template | **Open** | ~32% D; lead 1024×734 + body blocks |

**Merges:** PR **#8** templates (`cf2d7149`) merged. Meta branch `b4e15df2` **not** merged (head-only delta; re-test before merge).

---

## Template scores — before → after (Mac pack, DDEV `http://df-impact.ddev.site`)

Viewport/chrome values are **medians** from `PARITY_TEMPLATE=* DRUPAL_BASE_URL=http://df-impact.ddev.site npm run parity:compare-template`.

| Template | Viewport D (before → after) | Viewport T | Viewport M | Chrome D (before → after) |
|----------|----------------------------:|-----------:|-----------:|--------------------------:|
| **homepage** | 32.6% → **32.6%** | 34.8% → 34.8% | 33.5% → 33.6% | 12.9% → **12.9%** |
| **issue** | 68.0% → **68.0%** | 66.1% → 66.0% | 62.9% → 63.0% | 11.6% → **11.6%** |
| **article** | 31.7% → **31.7%** | 36.3% → 36.3% | 27.5% → 27.5% | 12.7% → **12.7%** |
| **in_brief** | 19.8% → **19.8%** | 13.2% → 13.2% | 15.3% → 15.3% | 12.7% → **12.7%** |
| **archive** | 30.3% → **30.3%** | 56.0% → 56.0% | 36.8% → 36.8% | 12.9% → **12.9%** |
| **landing_page** | 34.4% → **34.4%** | 40.1% → 40.1% | 37.6% → 37.5% | 12.7% → **12.7%** |

**PostX takeaway:** Order/set now driven by editor weights + WP backfill (102 issue + 10 homepage nodes on DDEV). Homepage latest nids **434, 445, 446** match WP post-id order. **Pixel medians unchanged** — remaining gap is images/fonts/content layout, not sort.

**Chrome takeaway:** Footer uses `USNewsBadge2627_146.png` + `USNewsChildrens_Footer.png` (byte-synced from live WP). Desktop footer diff ~**9.9%** vs header ~**16%** on `/`. Target ≤5% still blocked on mega menu + font rasterization.

---

## Full URL parity run `2026-10-29T00-20-11-819Z`

Log: `/tmp/parity-full-738.log`

| Metric | Value |
|--------|------:|
| Playwright tests passed | **691** (~1.1h) |
| **urlParityPercent** | **98.70%** |
| **metaMatchPercent** (smoke vs baseline) | **~14.5%** |
| **failureCount** | **753** (pixel/text/meta gates; not bulk 404s) |

---

## URL parity — 9 failing paths (792/801 = 98.9%)

| Path | Drupal | WordPress | Cause |
|------|--------|-----------|--------|
| `/category/drug-development/page/2` | **200** (after paginate fix) | 301 | Was 404 — fixed via `TopicCategoryArchivePaginateSubscriber` |
| `/category/drug-development/page/3` | **200** | 301 | Same |
| `/category/drug-development/page/4` | **200** | 301 | Same |
| `/category/pediatrics` | 404 | 301 | No **topics** term `Pediatrics` (only `cancer_types`) |
| `/stories` | 404 | 404 | Legacy; unmigrated |
| `/tag/digital-exclusives` | 404 | 301 | Tag archives N/I |
| `/tag/late-fall-2022` | 404 | 301 | Tag archives N/I |
| `/tag/late-fall-2022/page/2` | 404 | 301 | Tag archives N/I |
| `/topics` | 404 | 404 | Legacy hub |

Report: `parity/reports/url-parity/check-1793238507941.json` (re-run `parity:url:check` after pager fix).

---

## Key commands

```bash
cd drupal && ddev drush scr web/modules/custom/df_migrate/scripts/backfill_postx_sort_weights.php
cd parity && DRUPAL_BASE_URL=http://df-impact.ddev.site PARITY_TEMPLATE=issue npm run parity:compare-template
cd parity && npm run parity:capture-template-samples   # Mac owner, live WP
```

---

## Files touched this pass

- PostX: `field_*_sort_weight` sync, `views.view.issue_articles.yml`, homepage views, `backfill_postx_sort_weights.php`, `df_setup_update_10025`, `hook_views_query_alter`
- Chrome: `wp-chrome-footer.html.twig`, new badge PNGs under `files/wp-images/`, `df_impact.theme` logo i0.wp.com srcset, `parity/lib/page-utils.mjs` font load
- Archive: `TopicCategoryArchivePaginateSubscriber`, inbound path processor
- DDEV: `bind_mounts` → `/var/www/parity`

---

## Next (resume here)

1. Header chrome: mega menu computed-style diffs (`chrome-structural-selectors.json`); consider vendoring menu font metrics.
2. Landing: migrate/sync Gutenberg for `/about`, `/about-impact` + banner media.
3. Archive: row count / thumb styles on category listings; add **Pediatrics** topic mapping.
4. Article: top diff bands on both sample URLs; `wp_article_lead_1024x734` vendored files.
5. Re-run `parity:url:check` and update failure table when pediatrics/tags addressed.
