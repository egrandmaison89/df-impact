# Mac integrator — `df_setup` hook registry (binding)

Authoritative rules for merges onto `cursor/phase4-chrome-behavior-0b67` (PR #5).

## Frozen numbered hooks (10027–10035 only)

**Policy (effective now):** **No new `df_setup_update_*` numbers.** Schema cap **`10035`**. All further data/backfill work uses **name-based** functions in **`df_setup.post_update.php`** (Drupal tracks by name, not order).

**Reject any branch that:**

- Adds **`df_setup_update_10036`** or higher, or
- **Redefines 10027–10035** differently from phase4.

| Hook | Owner on phase4 | Purpose |
|------|-----------------|--------|
| **10027** | **#22 / #27** | Issue fixture backfill |
| **10028** | **#23** (partial) | `/about` staff sync |
| **10029** | **#25** (partial) | Gutenberg About body |
| **10030** | **#25** (partial) | Staff sync on Gutenberg body |
| **10031–10033** | — | **Never numbered on phase4** → **#23 post_updates** |
| **10034** | **#24** | Search parity (`df_secondary`, `search-legacy`) |
| **10035** | **#24** | Home landing / `page.front` |

**Upcoming post_updates (by name, on merge branches):**

| PR | Was / topic | Delivery |
|----|-------------|----------|
| **#23** | 10031–10033 | `df_setup_post_update_*` content-sweep backfills |
| **#25** | cover seed | `df_setup_post_update_*` media/cover seed |
| **#10** | `is_light` | **config import** + `df_setup_post_update_*` backfill (**stacked on #25**) |

## Merge order (whole merges only)

1. **#23** whole — **merged** (`ceff3720` ← `193ffdcf`)
2. **#25** whole — **merged** (`5b423ed8` ← `4884c8ab`); pass-3 live-WP CS 60/60
3. **#29** whole — **merged** (`83f35352` ← `efe645952`); sweep-801 follow-up
4. **DB convergence** — **merged** on phase4 (`e0a4f1f4`); `_df_setup_run_post_migrate_integrator_convergence()` + About canonical body
5. **#32** whole — **merged** (`ef3e2714` ← `cursor/admin-chrome-e2e-ce7f`); site-admin header/footer/hero + `admin-e2e` Playwright
6. **#34** whole — **merged** (`91b165bf`); admin-e2e teardown + `system.site:page.front: /homepage` + `FixHomeFrontPageConfigSubscriber` (cim-safe `/` 200)
7. **#35** whole — **merged** (`caf402c2` ← `cursor/editor-e2e-stable-ce7f`); issue-nav **Dynamic Page Cache** tags + publish invalidation; editor/admin e2e without `drush cr`; integrator follow-up: unpublish/archive nav bust + admin-e2e hero restore via entity save
8. **#30** whole — **merged** (`4f8ca897` ← `cursor/fall-2026-links-media-header-5b82`); Fall 2026 link/media repair, FA solid webfont (GTranslate icon), lead figcaption + In Brief photography credits, `parity:audit:drupal-links`; bootstrap runs `_df_setup_run_post_migrate_integrator_convergence()` (fresh-VM category archive sweep fix)
9. **#31** whole — **merged** (`b435badd` ← `cursor/visual-chrome-pagination-d748`); share/pagination/In Brief/tablet chrome; single **main/footer inset** in `drupal-overrides.css`; In Brief photo credit via `df_impact_in_brief_photo_credit_display()` (`field_photo_credit` then manifest)
10. **#10** whole — **merged** (`46dfef49` ← `cursor/meta-head-behavior-0751`); `field_issue_banner_is_light` config + post_update backfill; browse-issues cover inline styles; behavior/nav `is-light` parity
11. **#28** whole — article variants — **parked** (rebase after #31; agent archived)

**Next:** **#28** only when rebased and a **green gate table** is available. **Integrator idle** — no speculative work.

**#33 dropped** (agent stopped; cost cut). **Do not merge #33.** Optional later (**Eric must ask first**, after **#31**): cherry-pick three small pieces from PR #33 only — (1) digital exclusives **GivingTuesday-first** order + refreshed refpack, (2) **`/digital-exclusives` 404** + baseline, (3) idempotent **About** body. Until then, no #33 work.

**Done on phase4:** **#22/#27**, **#24**, **#23** (`ceff3720`), **#25** (`5b423ed8`), **#29** (`83f35352`), **convergence** (`e0a4f1f4`), **#32** (`ef3e2714`), **#34** (`91b165bf`), **#35** (`caf402c2` + integrator gate follow-up), **#30** (`4f8ca897`), **#31** (`b435badd`), **#10** (`46dfef49`).

**Registry tip (integrator):** **`2d1122b9`** on `cursor/phase4-chrome-behavior-0b67` (P0: logged-in admin toolbar/tabs; order-safe `in_brief_excerpt` post_update).

**P0 integrator follow-up (`2d1122b9`):** Scope `drupal-overrides.css` admin chrome hiding to `body:not(.user-logged-in)`; enable `df_impact_primary_local_tasks` + show `page.highlighted` for logged-in users only; `_df_setup_repair_link_default_view_formatters()` before `FieldConfig::save()` in `df_setup_post_update_in_brief_excerpt`; admin-e2e toolbar/Edit/`/admin/content` assertions.

**Generated parity gate output** (`parity/reports/content-sweep/`, smoke, url-parity, etc.) is **gitignored** — do not commit sweep JSON/CSV from PR branches.

**Post_updates (combined on phase4):** #23 four + #25 `seed_issue_covers`, `seed_issue_banners` + **#30** `repair_internal_links_and_media`, `ensure_df_secondary_search` + **#10** `issue_banner_is_light`.

On `df_setup.module` conflicts with #22, **keep #22’s side**.

## PR #25 (merged)

Whole merge at **`5b423ed8`** from `cursor/gutenberg-hover-archive-2b1b` @ **`4884c8ab`**. Hold cleared: pass-3 live-WP CS **60/60**, diff audit (reports-only deletions).

Computed-style acceptance on branch: `npm run parity:computed-style-diff:full:live-wp` (not refpack/pass-3 totals).

**Post-merge:** if `cim` resets `system.site:page.front`, run `_df_setup_ensure_home_landing()` (or re-verify anonymous `/` is **200**).

## PR #29 (merged)

Whole merge at **`83f35352`** from **`cursor/sweep-801-followup-ce7f`** @ **`efe645952`**.

**Post_updates added:** `ensure_about_impact_body`, `late_fall_2022_tag_links`, `late_fall_2022_byline_placement`, `late_fall_2022_export_byline_trim` (plus all prior #23/#25 post_updates).

**WP 404 parity:** `/topics`, `/late-fall-2022-issue` → Drupal **404** (see `parity/config/wp-live-404-paths.json`).

## Post-merge checklist (DB at schema 10035)

Run after **every** whole merge onto phase4 (#29, #30, #31, #28, #10, etc.).

**#30 gate extras:** `npm run parity:audit:drupal-links` (0 issues); article-variants **16/16** on fresh bootstrap (WP fixtures); pediatric brain tumor target → live WP `/2025/06/janower-family-drives-pediatric-brain-tumor-research-forward`.

**After #31 merge:** exactly one main/footer inset block in overrides (`#28` must not re-add its copy when rebased):

```bash
grep -c 'main/footer content column' drupal/web/themes/custom/df_impact/css/drupal-overrides.css
# expect: 1
```

```bash
cd drupal
./vendor/bin/drush cim -y              # when branch ships config
./vendor/bin/drush updb -y
./vendor/bin/drush updatedb:status    # must show no pending updates
./vendor/bin/drush php:script web/modules/custom/df_setup/scripts/verify_df_setup_schema.php
./vendor/bin/drush php:script web/modules/custom/df_setup/scripts/verify_df_setup_post_updates.php
# After #34: `cim` should leave `page.front` as `/node/NID` via FixHomeFrontPageConfigSubscriber (sync ships `/homepage`).

cd ../parity
npm run parity:smoke:200                              # 0 fails
npm run parity:template-samples:http                    # 19/19
npm run parity:url:check                              # 801/801
PARITY_CONTENT_SWEEP_NO_LIVE_WP=1 npm run parity:content:sweep   # 801/801 text98 among comparable (target)
npm run parity:issue-live-text:cache                    # 15/15 (0 below98)
npm run parity:content:sweep:issues:cache               # 15/15 text98
npx playwright test --project=editor-e2e                # green
npx playwright test --project=admin-e2e               # green (after #32)
cd ../drupal && ./vendor/bin/drush -r web df-editor:test-issue-e2e   # 10/10
```

**`/browse-issues` gate:** live WP and Drupal must both expose **15** `figure.issue-cover` elements (XPath: `//figure[contains(concat(' ', normalize-space(@class), ' '), ' issue-cover ')]`). Quick check:

```bash
curl -s http://127.0.0.1:8888/browse-issues | php -r '$d=new DOMDocument(); @$d->loadHTML(stream_get_contents(STDIN)); $n=(new DOMXPath($d))->query("//figure[contains(concat(\" \", normalize-space(@class), \" \"), \" issue-cover \")]"); echo $n->length, "\n";'
# expect: 15
```

**Issue gates:** committed `parity/cache/wp-issue-pages` + refpack only (`PARITY_ISSUE_WP_CACHE_ONLY=1`).

### PR #23 (merged)

Whole merge onto phase4 at **`ceff3720`**. Pre-merge gate was `parity:url:check` **801/801** on `cursor/content-sweep-ce7f`.

## Phase4 reference state

| Check | Expected |
|-------|----------|
| `df_setup` schema | **10035** |
| Numbered hooks in code | **10027–10030**, **10034–10035** only |
| `df_setup.post_update.php` | name-based only beyond cap |
| Cache-only issue gates | **15/15** both scorers |
