# PR #31 integrator gates

Branch: `cursor/visual-chrome-pagination-d748` (rebased on PR **#30** `4f8ca897`)  
HEAD: `8f73d370`  
Bootstrap: drop DB + `scripts/repro/bootstrap-vm.sh` (#30 two-phase `cim`, convergence, `search-api:index`, `_df_setup_ensure_home_landing`)  
VM: `DRUPAL_BASE_URL=http://127.0.0.1:8888`

## Gate table

| Gate | Result |
|------|--------|
| `drush updatedb:status` | none pending |
| `verify_df_setup_schema.php` | OK (10035, `update_10027_applied` set) |
| `verify_df_setup_post_updates.php` | OK |
| `/` HTTP | 200 |
| `/browse-issues` `figure.issue-cover` | 15 |
| `parity:smoke:200` | 0 fails |
| `parity:template-samples:http` | 19/19 |
| `parity:url:check` | 801/801 |
| cache-only `parity:content:sweep` | **801/801** text≥98% |
| `parity:issue-live-text:cache` | **15/15** compared, below98 0 |
| `parity:content:sweep:issues:cache` | **16/16** text98 |
| `parity:article-variants:compare` | **16/16** |
| Playwright `admin-e2e` | 2/2 |
| Playwright `editor-e2e` | 2/2 |
| `drush df-editor:test-issue-e2e` | 10/10 |
| Homepage behavior (lite=0, cross-site) | Playwright **3/3**; tablet **pass**; summary **failCount 1** (desktop `navHover` — pre-existing vs #30, not #31 CSS) |

## Stack notes

- In Brief **Photography by…** credit: single implementation from **#30** (`field_photo_credit` + manifest fallback); #31 duplicate removed on rebase.
- Eric QA retained: share, WP query pagination, In Brief spacing, single main/footer inset (`max(1.75rem, 5vw)`).
