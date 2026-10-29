# Meta / head / behavior / URL agent status

Branch: `cursor/meta-head-behavior-0751` @ **`a54a92b6`** (rebased onto phase4 **`6abb0698`**) | PR: https://github.com/egrandmaison89/df-impact/pull/10  
Base: `cursor/phase4-chrome-behavior-0b67` | **`is_light` only** — home/hero via phase4 `#32` + `df_setup_post_update_ensure_home_landing_published()` (not in #10 post_update).

## Site owner QA (Eric / DDEV) — 2026-10-29

| Issue | Fix | Verify |
| --- | --- | --- |
| Scrolled header collapses (sticky `.scrolled` on shell) | `wp-chrome-behaviors.js`: only `.header-logo` gets `.scrolled`; shell uses `header--scrolled` only. Removed duplicate sticky in `drupal-overrides.css`. Guard CSS on `.desktop-header.scrolled`. | Cross-site `headerLayout600` / `headerLayout2000` at desktop **1440**, tablet **768**, mobile **375** on all template paths (article, issue, in-brief, archive, landing) |
| Two GTranslate selects | Static WP markup kept; `gtranslate-init.js` loads **lib only** (no `dropdown.js` duplicate). Hide non-`.gt_selector` selects. | `gtranslate.visibleSelects` in header layout compare (expect 1) |
| Vimeo/YouTube URL text in body | `ArticleBodyCleaner::convertVideoEmbedsToIframes()` + `drush php:script …/df_backfill_article_body.php` (7 bodies updated on VM) | Article `more-than-a-building-name` shows iframe |

**Visual agent:** `bc-1df35d61` — re-run DDEV homepage scroll + article embed spot-check after pull.

## Resume behavior mismatch work

1. `git pull origin cursor/meta-head-behavior-0751 && cd drupal/web && ../vendor/bin/drush cr`
2. Smoke: `cd parity && DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:behavior:smoke`
3. Templates: `npm run parity:behavior:templates` → `npm run parity:behavior:report-diff`
4. Read `reports/behavior/<runId>/wp-drupal-behavior-summary.json` → `mismatchRowsByTemplate`, `totalMismatchRows`

## Strict cross-site behavior gate

- **Assertion:** `cross.diff.zero_mismatch_rows` (includes `headerLayout600`, `headerLayout2000` on every chrome-header template path × 3 breakpoints).
- **Capture gate:** WP timeouts → `cross.wp.snapshot` and `captured: false`; summarize exits **1** until `caseCount === expectedCases` (currently **51** = 17 paths × 3 BPs) and `incompleteCaseCount === 0`. Retry: `PARITY_BEHAVIOR_RUN_ID=<run> node scripts/retry-behavior-incomplete.mjs`.
- **Nav `backgroundColor` @768/375:** Evidence `reports/behavior/wp-nav-bg-evidence/EVIDENCE.md`. WP solid nav follows **`is-light` issue hero** (+ `/browse-issues`). Drupal: `field_issue_banner_is_light` in **df_setup config/install**; existing sites: **`drush updatedb`** runs `df_setup_post_update_issue_banner_is_light()`. Helper: `drush scr …/backfill_issue_banner_is_light.php`.
- **Hover compare:** `hover.changed` parity + color/bg/transform when either side changes (~3/255 tolerance).
- **Body link hover:** content paths only (`/YYYY/MM/`, `/in-brief`, `-issue`).

## Compare exclusions (documented)

| Area | Rule |
| --- | --- |
| `hero` | Homepage only |
| `headerLayout600`, `headerLayout2000` | All template paths with chrome header (not homepage-only) |
| `pagerHover`, `categoryLinkHover`, `inBriefButtonHover` | Optional (`optionalHoverCompare` — no mismatch when selectors absent) |
| `bodyLinkHover` | Content paths only |
| `issuesDropdown` | Desktop only |
| `navDefault` | Full compare all breakpoints (see wp-nav-bg-evidence) |
| `mobileMenu` | Mobile only |
| Widget inventory | Scoped to `main`; consent roots excluded |

## Nondeterminism

- Consent script route abort + DOM removal before hover capture (`dismissOverlays`, init observer).
- WP load timeouts → `cross.wp.snapshot` (not a mismatch row).

## Gates (VM)

| Gate | Status |
| --- | --- |
| `parity:head:manifest` | **773/773** |
| `parity:url:check` | **801/801** |
| `parity:behavior:templates` | **PASS** `2026-10-29T12-cross18-final`: **51/51** cases, **0** incomplete, **0** mismatch rows (17 paths × 3 BPs). Nav bg keyed off `field_issue_banner_is_light` / `is-light` hero. |
| `parity:smoke:200` | **40 paths, 0 fails** (`reports/smoke-200/smoke-1793267221975.json`) |
| Embeds backfill | VM run 2026-10-29: **before** 16 fields (15 article, 1 in_brief) with vimeo/youtube markers; **after** 1 in_brief field updated (articles already iframe-backed). Script: `drush php:script modules/custom/df_migrate/scripts/df_backfill_all_embeds.php` |

## Hover/focus (More Stories, pager, category, In Brief band)

- Harness: `pagerHover`, `categoryLinkHover`, `inBriefButtonHover`, `cardHover` in cross-site diff (optional when DOM missing).
- Theme: `theme.css` — In Brief band + banner CTA hover/focus; ultp card title link hover; archive pager/category `:focus-visible`.

Updated: 2026-10-29T17:05Z

## Post-rebase integrator gates (VM @ `a54a92b6`)

| Gate | Result |
| --- | --- |
| `drush updatedb:status` | **none pending** |
| `verify_df_setup_schema.php` | **10035 / OK** |
| `verify_df_setup_post_updates.php` | **OK** |
| `verify-wp-port-assets.sh` @ `:8888` | **20/20** |
| `GET /` | **200** |
| `/browse-issues` `issue-cover` | **15** |
| `parity:smoke:200` | **40 paths, 0 fails** |
| `parity:template-samples:http` | **19/19** |
| `parity:url:check` | **801/801** |
| `parity:content:sweep` (cache-only) | **801 comparable; 10 paths &lt;0.98 text** (category archives — pre-existing sweep debt) |
| `parity:issue-live-text:cache` | **15/15, below98=0** |
| `parity:content:sweep:issues:cache` | **15/15 text98** |
| `playwright` `admin-e2e` | **2/2** |
| `playwright` `editor-e2e` | **1/2** (article save: entity reference node:785 — investigate on phase4) |
| `drush df-editor:test-issue-e2e` | **12/12 PASS** |
| Behavior `2026-10-29T12-cross18-final` | **51/51, 0 mismatch** (pre-rebase run; code delta is nav/is_light only) |
