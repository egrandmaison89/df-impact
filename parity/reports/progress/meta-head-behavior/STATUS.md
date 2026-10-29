# Meta / head / behavior / URL agent status

Branch: `cursor/meta-head-behavior-0751` | PR: https://github.com/egrandmaison89/df-impact/pull/10  
Base: `cursor/phase4-chrome-behavior-0b67` | Integration: PR #12 flow

## Site owner QA (Eric / DDEV) — 2026-10-29

| Issue | Fix | Verify |
| --- | --- | --- |
| Scrolled header collapses (sticky `.scrolled` on shell) | `wp-chrome-behaviors.js`: only `.header-logo` gets `.scrolled`; shell uses `header--scrolled` only. Removed duplicate sticky in `drupal-overrides.css`. Guard CSS on `.desktop-header.scrolled`. | Cross-site `headerLayout600` / `headerLayout2000` + `wp/drupal-header-scroll-*.png` on `/homepage` |
| Two GTranslate selects | Static WP markup kept; `gtranslate-init.js` loads **lib only** (no `dropdown.js` duplicate). Hide non-`.gt_selector` selects. | `gtranslate.visibleSelects` in header layout compare (expect 1) |
| Vimeo/YouTube URL text in body | `ArticleBodyCleaner::convertVideoEmbedsToIframes()` + `drush php:script …/df_backfill_article_body.php` (7 bodies updated on VM) | Article `more-than-a-building-name` shows iframe |

**Visual agent:** `bc-1df35d61` — re-run DDEV homepage scroll + article embed spot-check after pull.

## Resume behavior mismatch work

1. `git pull origin cursor/meta-head-behavior-0751 && cd drupal/web && ../vendor/bin/drush cr`
2. Smoke: `cd parity && DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:behavior:smoke`
3. Templates: `npm run parity:behavior:templates` → `npm run parity:behavior:report-diff`
4. Read `reports/behavior/<runId>/wp-drupal-behavior-summary.json` → `mismatchRowsByTemplate`, `totalMismatchRows`

## Strict cross-site behavior gate

- **Assertion:** `cross.diff.zero_mismatch_rows` (includes `headerLayout600`, `headerLayout2000` on homepage).
- **Hover compare:** `hover.changed` parity + color/bg/transform when either side changes (~3/255 tolerance).
- **Body link hover:** content paths only (`/YYYY/MM/`, `/in-brief`, `-issue`).

## Compare exclusions (documented)

| Area | Rule |
| --- | --- |
| `hero`, `headerLayout600`, `headerLayout2000` | Homepage only |
| `bodyLinkHover` | Content paths only |
| `issuesDropdown` | Desktop only |
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
| `parity:behavior:templates` | Cross6 run in progress / re-run after QA fixes |

Updated: 2026-10-29T05:15Z
