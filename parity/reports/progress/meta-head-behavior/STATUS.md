# Meta / head / behavior / URL agent status

Branch: `cursor/meta-head-behavior-0751` | PR: https://github.com/egrandmaison89/df-impact/pull/10  
Base: `cursor/phase4-chrome-behavior-0b67` | **Stacked on PR #25** `cursor/gutenberg-hover-archive-2b1b` (rebased 2026-10-29)

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
| `parity:behavior:templates` | **Active:** `2026-10-29T12-cross18-final` (tmux `behavior-cross18`, field-driven nav bg). |
| `parity:smoke:200` | **40 paths, 0 fails** (`reports/smoke-200/smoke-1793267221975.json`) |
| Embeds backfill | VM run 2026-10-29: **before** 16 fields (15 article, 1 in_brief) with vimeo/youtube markers; **after** 1 in_brief field updated (articles already iframe-backed). Script: `drush php:script modules/custom/df_migrate/scripts/df_backfill_all_embeds.php` |

## Hover/focus (More Stories, pager, category, In Brief band)

- Harness: `pagerHover`, `categoryLinkHover`, `inBriefButtonHover`, `cardHover` in cross-site diff (optional when DOM missing).
- Theme: `theme.css` — In Brief band + banner CTA hover/focus; ultp card title link hover; archive pager/category `:focus-visible`.

Updated: 2026-10-29T12:20Z
