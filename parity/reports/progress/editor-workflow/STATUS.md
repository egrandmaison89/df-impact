# Editor workflow agent status

Branch: `cursor/editor-workflow-ce7f`  
Base PR target: `cursor/phase4-chrome-behavior-0b67`

## Resume checklist

1. `git pull origin cursor/editor-workflow-ce7f`
2. Bootstrap DB if needed: `bash scripts/repro/bootstrap-vm.sh`
3. `cd drupal/web && ../vendor/bin/drush cr`
4. Run workflow test: `../vendor/bin/drush df-editor:test-publish-workflow`
5. Parity gates: `cd parity && DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:head:refpack && npm run parity:url:check`

## Deliverables

| Item | Status |
| --- | --- |
| `df_editor` module (roles, site chrome, issue publish) | In progress |
| Dynamic Issues nav / browse-issues sort | In progress |
| Hero heading field on issue | In progress |
| Editor docs | Pending |
| Automated publish workflow test | Pending |

## Decisions

- **Homepage hero**: Video, poster, and heading live on the **latest published issue** (site admins edit via the issue form). Web editors can edit hero fields only if they can edit issues; by default issue create/edit is limited—see role matrix in docs.
- **Site admin vs web editor**: Exported roles `site_admin` and `web_editor` replace day-to-day use of legacy `editor` / `issue_manager` (legacy roles kept for migration compatibility).

Updated: 2026-10-29T05:15Z
