# Issue landing + listings agent (`cursor/issue-landing-parity-185b`, PR #22)

**Coordination:** Editor / content sweep → `cursor/content-sweep-ce7f`.  
**Isolation write-up:** `ISOLATION-2026-10-29.md`.

## Deterministic issue backfill

| Item | Location |
| --- | --- |
| Fixture JSON | `drupal/web/modules/custom/df_migrate/data/issue_page_wp_fixture.json` |
| Regenerate | `build-issue-page-fixture.mjs` + optional `fetch-wp-issue-pages.mjs` |
| Deploy hook | `df_setup_update_10027()` |
| Replay test | `test_issue_fixture_replay.php` |

## Text parity (15 comparable issue landings)

| Scorer | Result | Artifact |
| --- | --- | --- |
| **`issue-live-text-sweep.mjs`** | **15/15 ≥98%**, min **100%** | `issue-live-sweep-1793272021647.json` |
| **`run-content-sweep.mjs`** (issue rows use same Playwright main-text metric) | **15/15 ≥98%**, min **100%** | `sweep-1793273825991.json` |
| `/late-fall-2022-issue` | Non-comparable (WP archive error in cache) | — |
| `/browse-issues` | 100% vs ref pack | (prior run) |

Harness: `npm run parity:issue-live:sweep`, `npm run parity:content:sweep` with `CONTENT_SWEEP_PATHS` listing issue aliases; helper `npm run parity:issue:compare-extract -- /fall-2025-issue`.

## Fixes in this push

- Playwright issue text shared with content sweep; issue-cache WP HTML prioritized over frozen ref.
- Stable fixture `setContent` + `article.issue-page` extract root.
- PostX card titles: decode entities / strip `<sup>` for WP text parity.

## Gates

- `parity:template-samples:http`
- `parity:smoke:200`
