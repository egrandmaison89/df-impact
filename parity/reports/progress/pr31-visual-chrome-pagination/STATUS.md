# PR #31 integrator gates

Branch: `cursor/visual-chrome-pagination-d748`  
Phase4 base: `dc4e7355` (includes #34)  
HEAD: `a9554b4a34ded6f3392b0dc973b9e62c75a2f194`  
Verified: fresh VM bootstrap (drop DB + migrate recovery + `updb` replay 10027–10035 for schema marker), `DRUPAL_BASE_URL=http://127.0.0.1:8888`

## Gate table

| Gate | Result |
|------|--------|
| `drush updatedb:status` | none pending |
| `verify_df_setup_schema.php` | OK (schema 10035, `update_10027_applied` set) |
| `verify_df_setup_post_updates.php` | OK |
| `/` HTTP | 200 |
| `/browse-issues` `figure.issue-cover` | 15 |
| `parity:smoke:200` | 0 fails |
| `parity:template-samples:http` | 19/19 |
| `parity:url:check` | 801/801 |
| `PARITY_CONTENT_SWEEP_NO_LIVE_WP=1 parity:content:sweep` | **791/801** text≥98% (10 category archive paths below 98%; see note) |
| `parity:issue-live-text:cache` | **15/15** compared, min 1.0, below98 0 |
| `parity:content:sweep:issues:cache` | **16/16** text98 (issue manifest rows) |
| `parity:article-variants:compare` | 16/16 |
| Playwright `admin-e2e` | 2/2 green |
| Playwright `editor-e2e` | 2/2 green |
| `drush df-editor:test-issue-e2e` | 10/10 |
| Homepage behavior (`PARITY_BEHAVIOR_LITE=0`, cross-site) | Playwright **3/3** (desktop/tablet/mobile); **tablet pass**; cross-site summary **failCount 1** (desktop `navHover` color vs live WP) |

## Note (content sweep)

Below-98% paths (cache-only sweep):  
`/category/access-and-equity`, `/category/discovery-science`, `/category/drug-development` (+ `/page/2`, `/page/3`), `/category/grassroots-support`, `/category/immunotherapy`, `/category/pediatrics`, `/category/planned-giving`, `/category/prevention-and-early-detection`.

## Scope reminder

- Single main/footer inset block restored (`drupal-overrides.css`, WP `max(1.75rem, 5vw)` outer spacing).
- PNGs / generated JSON under `parity/reports/progress/` remain untracked (gitignore).
