# Editor workflow agent status

Branch: `cursor/editor-workflow-ce7f`  
Base PR target: `cursor/phase4-chrome-behavior-0b67`  
PR: https://github.com/egrandmaison89/df-impact/pull/15

## Resume checklist

1. `git pull origin cursor/editor-workflow-ce7f`
2. Bootstrap DB if needed: `bash scripts/repro/bootstrap-vm.sh`
3. `cd drupal/web && ../vendor/bin/drush cr`
4. Run workflow test: `../vendor/bin/drush df-editor:test-publish-workflow`
5. Parity gates (Drupal on `http://127.0.0.1:8888`):
   - `cd parity && DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:head:refpack`
   - `npm run parity:url:check`
   - `npm run parity:template-samples:http`

## Deliverables

| Item | Status |
| --- | --- |
| `df_editor` module (roles, site chrome, issue publish) | Done |
| Automatic **Current Issue** header link (`df_setup_get_latest_issue_nid()`) | Done (`CurrentIssueNav`) |
| Homepage hero admin entry (site settings + `/admin/config/df-impact/homepage-hero`) | Done |
| Dynamic Issues nav / browse-issues sort | Done |
| Hero heading field on issue + hidden from web editors | Done (form group + `NodeForm` fix) |
| Editor docs | Done (`docs/editors/*`) |
| Drush integration test | Done (`df-editor:test-publish-workflow`) |
| PHPUnit functional + kernel tests | Done (run functional against bootstrapped site; see test file headers) |
| VM bootstrap | `scripts/repro/bootstrap-vm.sh` ensures Home landing after migrate |
| `parity:head:refpack` | **21/21** (`compare-1793253029523.json`) |
| `parity:url:check` | **801/801** (`check-1793253012478.json`) |
| `parity:template-samples:http` | **19/19** all HTTP 200 |

## Decisions

- **Homepage hero**: Video, poster, and heading on the **latest published issue**; site admins edit via site settings shortcut or the homepage hero menu link.
- **Current Issue**: Menu item title only; URL is rewritten on every request—no manual menu updates at launch.
- **Site admin vs web editor**: Exported roles `site_admin` and `web_editor`.

Updated: 2026-10-29T05:55Z
