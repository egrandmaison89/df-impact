# VM visual parity — PR #25 integrator status

**Branch:** `cursor/gutenberg-hover-archive-2b1b` (rebased/merged **phase4** @ `ac138a96`, schema frozen **10035**).  
**Drupal:** `http://127.0.0.1:8888`  
**Baseline policy:** **`PARITY_WP_SOURCE=live`** for agent totals (`npm run parity:computed-style-diff:full:live-wp`). Refpack CS is diagnostic only (3–4× Σ inflation: document height, chrome padding, stale HTML).

**Updated:** 2026-10-29 (post-merge phase4 + post_update covers)

---

## df_setup hooks (integrator)

| Layer | Content |
|-------|---------|
| **10027** | Issue PostX backfill (PR #22, on phase4) |
| **10028–10030** | About staff + Gutenberg body (match phase4; no redefinition on #25) |
| **10034–10035** | PR **#24** search parity + home landing (phase4; **no 10036+**) |
| **post_update** | `df_setup_post_update_seed_issue_covers`, `df_setup_post_update_seed_issue_banners` in `df_setup.post_update.php` (scaffold + verify scripts from phase4) |

**VM check (schema 10035):** `drush php:script modules/custom/df_setup/scripts/verify_df_setup_schema.php` + `verify_df_setup_post_updates.php` → post_updates **OK**. Browse **`/browse-issues`**: **11** `figure.issue-cover` (covers from `field_cover_image`).

---

## Gates (2026-10-29, live Drupal)

| Gate | Result |
|------|--------|
| `parity:smoke:200` | **0** fails (`smoke-1793280656863.json`) |
| `parity:template-samples:http` | **19/19** (`check-1793280791731.json`) — `/digital-exclusives` via `DigitalExclusivesLegacyController` |
| `parity:head:refpack` (default **`HEAD_REFPACK_SCOPE=template`**, 18 slugs) | **16/18** — see below |
| `parity:head:refpack` **`HEAD_REFPACK_SCOPE=full`** (~37 pack folders) | **18/37** historical — extra pack pages (pager slugs, pediatrics, search/topics stubs, stale meta) |

### Head refpack — what it checks

Compares Yoast/meta + JSON-LD from **frozen** `parity/reference-pack/*/desktop/rendered.html` vs **live Drupal** HTML (`compare-head-meta.mjs`, `HEAD_REFPACK_CHECK_WP=0` on VM).

**Was it passing before?** **21/21** on a **curated ~21-slug** set pre–content-sweep pack expansion; after sweep **37** folders → **18/37** without template filter.

**Template scope failures (2):** `category_digital-exclusives`, `digital-exclusives` — **canonical/description/title match** after tag→category normalize; **jsonLd `thumbnailUrl`** differs (**CLLSociety** in frozen pack vs **Capri** lead on live Drupal archive). **Content drift in snapshot**, not routing — refresh refpack or accept for merge.

**Full-pack failures (19):** Drupal **404** on pack-only paths (`category_*_page_2`, pediatrics term), **search/topics** pack 404 vs real Drupal routes, description/canonical on ancillary category captures — out of PR #25 template scope.

---

## Computed-style — pass 2 vs pass 3 (live WP, same harness)

**Pass 2 reference:** `full-cs-pass2-after` desktop **`comparableSum`** (live WP, pre–#25 About/archive/footer work):

| Template | Pass 2 Σ |
|----------|---------:|
| homepage | 68 |
| issue | 114 |
| article | 67 |
| in_brief | 90 |
| landing_page | 94 |
| archive | 179 |

**Pass 3 (current code, live WP):** serial run **`REPORT_RUN_ID=full-cs-pass3-live-pr25`** via `parity:computed-style-diff:full:live-wp` (in progress on VM). Prior partial live run aligned with pass 2 on core templates (homepage/issue **68/114**); landing/archive improved with archive row + About Gutenberg. **Do not adopt refpack Σ as baseline.**

**Issue spring desktop (spot, live WP, post-banner + PostX sort):** **26** mismatches (`wpComparable` true). Top properties: **`main` / `.wp-site-blocks` h** (~436px — article count/body length), **footer y** (follows page height), **`.ultp-block-items-wrap .ultp-block-item` y/h** (~24/26px — mitigated with **`margin-top: 24px`** on issue PostX row).

---

## PR #25 merge readiness

| Criterion | Status |
|-----------|--------|
| Rebased on phase4 **10035** + post_update covers | **Yes** |
| Gates smoke + template-samples | **Green** |
| Head refpack template | **16/18** (2× jsonLd thumbnail drift — documented) |
| Live-WP CS vs pass 2 | **Run finishing**; spot checks favorable |
| PostX spring/summer | **Real fixes:** banners/covers, PostX sort/exclusions (phase4 **10027**), grid **margin-top**; residual **h** = content length |

**Verdict:** **Merge-ready for integrator** with known follow-ups: refresh DE archive refpack JSON-LD snapshot; complete full live-WP CS job for published pass2/pass3 table; optional refpack head full-pack cleanup in a later PR.

---

## Commands

```bash
cd drupal/web && ../vendor/bin/drush serve 127.0.0.1:8888
cd parity && DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:smoke:200
cd parity && DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:template-samples:http
cd parity && DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:head:refpack
cd parity && DRUPAL_BASE_URL=http://127.0.0.1:8888 REPORT_RUN_ID=full-cs-pass3-live-pr25 npm run parity:computed-style-diff:full:live-wp
drush php:script modules/custom/df_setup/scripts/verify_df_setup_post_updates.php
```
