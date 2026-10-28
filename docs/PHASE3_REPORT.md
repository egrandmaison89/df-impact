# Phase 3 report — content, archive IA, chrome, meta

**Branch:** `cursor/phase3-content-chrome-0b67` · **PR:** #3 · **Base:** `main` @ `32163d04` (Phase 2 squash)

**Status:** Phase 3 is **not complete** against owner release gates (pixel median ≤2%, p90 ≤5%, text ≥98%, meta/OG match). This PR is an **incremental, safe merge** for WXR-driven content, archive URL fixes, baseline capture fix, and metatag wiring. Chrome to 2%/5% remains follow-up work with frozen baseline + WXR template parts.

---

## 1. Content completeness (783 published WP posts + pages)

**Authoritative source:** `drupal/migration-data/private/wp-export.xml` (gitignored — staff emails).

**Regenerate committed migration JSON:**

```bash
cd drupal/migration-data
python3 process_wxr_data.py    # articles, in_brief, issues, taxonomies → df_migrate/data
```

**Field / pipeline decisions:**

| Topic | Decision |
| --- | --- |
| Post dates | WXR `Y-m-d H:i:s` → ISO `Y-m-d\TH:i:s` for migrate `format_date`. |
| Issue tags | WXR tag `count` is 0 → recomputed from post tag IDs before `process_wp_data.py`. |
| Newest issues | WXR issue **pages** (`/{season}-{year}-issue`) merged into `issues.json` with `path_alias`. |
| In Brief vs article | Same rules as REST (`In Brief {Season} {Year}` tags). |
| Permalinks | `drupal_alias` mirrors WordPress path (Phase 2 rules). |

### Reconcile (`reconcile_wxr_drupal.py`)

Counts **783** published posts + pages from WXR. View landing paths (`/issues`, `/in-brief`, `/`, etc.) count as matched when served by Drupal Views (see §2).

| Metric | Phase 2 | Phase 3 |
| --- | ---: | ---: |
| **Matched** | 664 | **781** |
| **Missing** | 119 | **2** |

**Still missing (intentional):**

| Path | WP title | Why |
| --- | --- | --- |
| `/pagination-test` | Pagination test | WP QA page — not migrated |
| `/video-test` | Video Test | WP QA page — not migrated |

**Drupal published nodes after import:** 436 articles, 323 in brief, 16 issues (= **759** posts; remainder of 783 are landing/list pages via Views, not nodes).

**Migration import (this environment):** `df_articles` 63 created + 376 updated; `df_in_brief` 50 created + 273 updated; `df_issues` 2 created + 14 updated; **0 failed** after date fix.

---

## 2. Archive & listing IA parity

| WordPress path | Purpose | Drupal (Phase 3) | HTTP (local) |
| --- | --- | --- | ---: |
| `/issues` | Stories Archive | **`stories_archive`** view (articles, 12/page, teaser) | 200 |
| `/browse-issues` | Issue grid | **`browse_issues`** — path fixed from wrongly sharing `/issues` | 200 |
| `/past-issues` | Recent issues | **`past_issues`** view (issue teasers) | 200 |
| `/in-brief` | In Brief archive | **`in_brief_listing`** view (unchanged) | 200 |
| `/`, `/homepage` | Home | Front page + **`WordpressHomepageRouteSubscriber`** (no redirect strip for `/` or `/homepage`) | 200 |

**Listing order / pagination:** Stories archive uses created DESC, 12 per page (full pager). Past/browse issues use year/season DESC (same as browse grid). **Visual/listing parity vs WP** (exact card order, filters) not yet at 2% pixel — see §4.

**Not in reconcile set:** `/about`, `/home` (legacy) — still **404 on Drupal** if in manifest; add basic pages later if required.

---

## 3. Chrome — before vs after (smoke sample, live WP)

**Run IDs:** Phase 2 smoke `2026-10-28T15-49-13-236Z` · Phase 3 smoke `2026-10-28T17-29-00-277Z` (`npm run parity:smoke`, ~25 URLs × 3 breakpoints).

**Caveat:** Cloud/datacenter runs still hit **SiteGround captcha** on some live WP URLs (`wpReachable` 23–24/25). **Owner Mac baseline** `df-impact-2026-10-28` (0 captcha on smoke capture) is the trustworthy reference for text/meta/pixel going forward (`PARITY_WP_BASELINE=…`).

**Pixel diff median / p90** (target: median ≤**2%**, p90 ≤**5%**)

| Template | BP | Phase 2 median | Phase 3 median | Phase 2 p90 | Phase 3 p90 | Gate |
| --- | --- | ---: | ---: | ---: | ---: | --- |
| article | desktop | 15.5% | **19.2%** | 32.6% | **33.2%** | Fail |
| article | tablet | 19.6% | **27.3%** | 31.3% | **31.9%** | Fail |
| article | mobile | 33.8% | **20.2%** | 36.3% | **26.7%** | Fail |
| issue | desktop | (403 / N/A) | **42.1%** | — | **46.4%** | Fail |
| issue | tablet | N/A | **49.8%** | — | **69.7%** | Fail |
| issue | mobile | N/A | **37.4%** | — | **48.6%** | Fail |
| homepage | desktop | (see Phase 2) | **34.0%** | — | **50.9%** | Fail |
| homepage | tablet | — | **17.1%** | — | **17.1%** | Fail |
| homepage | mobile | — | **41.8%** | — | **56.0%** | Fail |
| archive | desktop | — | **14.4%** | — | **14.4%** | Fail |

**Main-content text similarity median** (target ≥**98%**)

| Template | BP | Phase 2 | Phase 3 | Gate |
| --- | --- | ---: | ---: | --- |
| article | desktop | ~47% (captcha/noise) | **95.9%** | Fail (just under 98% on some rows) |
| article | mobile | ~94% | **48.5%** | Fail |
| issue | desktop | N/A | **47.4%** | Fail |
| homepage | desktop | low | **26.0%** | Fail |

**Drupal reachability (smoke):** Phase 2 **13/25** desktop cases → Phase 3 **21/25** (dated paths + issues + archives).

**Chrome work in this PR:** WXR token doc (`wp_design_tokens.json`), header **20px** padding (WXR `impact-header`), existing header/footer templates unchanged structurally. **Not done:** supplement row markup, Issues dropdown from `wp_navigation`, mobile drawer parity, footer DEI strip fine-tuning, homepage hero from WXR blocks.

---

## 4. Meta & Open Graph parity

**Smoke run `2026-10-28T17-29-00-277Z`:** `evaluate-thresholds.mjs` reports **metaMatchPercent: 0%** (80 failures total including pixel/text/url).

**Why:**

- Landing/archive/home rows compare **View pages** without per-page Yoast from WXR.
- Live WP captcha/challenge titles pollute `<title>` when baseline not used.
- Article nodes: **`field_meta_description`** populated from WXR Yoast on migrate; **Metatag** defaults updated to prefer that field for description / OG / Twitter.

**After merge:** Re-run with `PARITY_WP_BASELINE=df-impact-2026-10-28` on owner Mac for meaningful meta/OG gates on articles/issues.

---

## 5. What is still failing and why

| Area | Failure | Reason |
| --- | --- | --- |
| **Release pixel gates** | All template×breakpoint medians ≫ 2% | Global chrome, hero, issue layouts, typography spacing vs WP |
| **Text ≥98%** | issue/home/archive/landing | Region extraction + layout differences; some mobile article rows |
| **Meta/OG** | 0% on smoke | Views + captcha + no baseline in CI |
| **URL parity (smoke)** | ~84% in evaluate | `/about`, `/home`, captcha-blocked WP, legacy manifest entries |
| **Full manifest** | Not re-gated | Needs `npm run parity` + baseline on Mac |
| **Media** | Phase 2 handled uploads | Phase 3 did not re-run full `df_media_images` import in report environment |

---

## 6. What is left to do (Phase 3+ / follow-up PRs)

1. **Frozen baseline:** Owner full capture `df-impact-2026-10-28`; share **lite zip** (`npm run parity:package-baseline-lite`) for agent text/meta; pixel on Mac or full PNGs via drive.
2. **Chrome:** Rebuild header (supplement row, Issues dropdown, drawer), footer (DEI + legal), homepage hero from `wp_template_part` / `wp_navigation` in WXR reference.
3. **Meta on listings:** View-level metatag defaults for `/`, `/issues`, `/browse-issues`, issue landings.
4. **Optional:** Basic pages for `/about` (and `/home` redirect) if manifest requires.
5. **Full `npm run parity`** with baseline — release gate.

**Baseline capture fix (in PR):** `parity/lib/parity-root.mjs` — no `import.meta` in Playwright spec (owner Mac Node 24 / CJS `package.json`).

---

## 7. Owner DDEV update after merge

Assumes **existing DDEV site** with database (not empty install). From repo root:

```bash
git pull origin main
ddev start
cd drupal
export DDEV_DB_PORT="$(ddev describe 2>/dev/null | sed -n 's/.*3306 -> 127.0.0.1:\([0-9]*\).*/\1/p' | head -1)"
DRUSH="./vendor/bin/drush -r web"

# Config (views, metatag, browse-issues path, new archive views)
${DRUSH} updatedb -y
${DRUSH} cim -y

# Optional: refresh migration JSON from your WXR (not in git)
# cd ../migration-data && python3 process_wxr_data.py && cd ../drupal

# Re-import content from committed df_migrate/data (no rollback needed if maps exist)
${DRUSH} migrate:import df_issues --update -y
${DRUSH} migrate:import df_articles --update -y
${DRUSH} migrate:import df_in_brief --update -y

# Paths + cache
${DRUSH} df-migrate:sync-wordpress-paths
${DRUSH} cr

# WordPress upload URL parity (same as bootstrap script)
mkdir -p web/sites/default/files/wp-content/uploads web/wp-content
ln -sfn ../sites/default/files/wp-content/uploads web/wp-content/uploads

# Optional: media from WXR (long; skip if already imported)
# ${DRUSH} migrate:import df_media_images --update -y
```

**Fresh empty DB:** use `scripts/repro/bootstrap-from-migrations.sh` (add `process_wxr_data.py` locally if WXR present).

**Verify:**

```bash
${DRUSH} sqlq "SELECT type, COUNT(*) FROM node_field_data WHERE status=1 GROUP BY type"
# Expect ~436 article, ~323 in_brief, ~16 issue

cd ../parity
npm run parity:smoke
# Or with baseline on Mac:
PARITY_WP_BASELINE=df-impact-2026-10-28 npm run parity:smoke
```

---

## 8. Merge recommendation

**Safe to merge now as an incremental step:** Yes.

- Does **not** remove Phase 2 harness, baseline mode, or acceptance docs.
- **Improves** content coverage (781/783 paths), archive URLs, migration pipeline, baseline capture on Mac, metatag wiring.
- Does **not** claim release readiness — pixel/meta gates still fail until chrome + baseline-driven parity work lands.

**Do not** use merge alone as release sign-off; run full parity with owner baseline after follow-up chrome PR(s).
