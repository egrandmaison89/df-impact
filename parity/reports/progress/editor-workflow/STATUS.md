# Editor workflow agent status

**PR:** https://github.com/egrandmaison89/df-impact/pull/17  
**Branch:** `cursor/editor-followups-ce7f` (on PR #5 @ `b51df612`, after merged PR #15 + Mac `entity:node/{nid}` menu install fix)  
**Supersedes:** PR #16 / `cursor/editor-workflow-ce7f` (large accidental parity deletions — do not use)

## Diff scope (vs PR #5)

Verified 2026-10-29:

```text
git diff --stat origin/cursor/phase4-chrome-behavior-0b67...HEAD
 19 files changed, 13411 insertions(+), 45 deletions(-)
```

- **No** `wp-images/` changes, **no** parity reference-pack / behavior harness deletions.
- Additions are mostly gate **report JSON** artifacts (`url-parity`, `head-meta`); code is `df_editor`, `df_setup` subscriber, theme preprocess, docs, bootstrap, template-samples HTTP script tweak.

## Follow-up verification

| # | Requirement | Evidence |
| --- | --- | --- |
| (a) | **Current Issue** auto-resolves to latest published issue | `CurrentIssueNav::applyCurrentIssueUrl()` in `df_impact_preprocess_page()` + menu install uses `entity:node/{nid}` only as seed URL (runtime rewrite wins). VM: `latest_nid=20` → resolved `/fall-2026-issue`, `matches_issue=true` (Drush eval 2026-10-29). |
| (b) | **Homepage hero** admin entry + docs | Routes: `/admin/config/df-impact/site-settings` (Homepage hero section + button), `/admin/config/df-impact/homepage-hero` → latest issue `#edit-df-homepage-hero`. `docs/editors/ADMIN_GUIDE.md` § Homepage hero. |
| (c) | **End-to-end publish** (web_editor, issue + 2 articles, public surfaces, restore) | `drush df-editor:test-publish-workflow` **pass** on VM (`http://127.0.0.1:8888`): creates drafts, publishes bundle, asserts Issues dropdown, Current Issue URL, homepage hero heading, `/browse-issues`, deletes test nodes, restores prior latest nid. PHPUnit: `IssuePublishWorkflowTest.php` (requires bootstrapped site + PHPUnit via `composer install`; not run in VM — no composer). |
| (d) | **403 + hero field access** for web_editor | Same Drush test: 403 on `df_editor.site_settings`, `footer_settings`, `homepage_hero`. Form: `df_homepage_hero` `#access` = FALSE (fields not at top level). Kernel: `IssueHeroAccessKernelTest.php`. VM spot-check 2026-10-29: all three routes **403**; `df_homepage_hero access=false`. |
| (e) | **URL 801/801** after full migrate | Migrations complete on VM; `df-migrate:sync-wordpress-paths` run. Fresh gate: `parity:url:check` → **801/801** (`check-1793253267396.json`). All **801** Drupal responses **HTTP 200** (no redirect). |

## Parity gates (VM `DRUPAL_BASE_URL=http://127.0.0.1:8888`, 2026-10-29)

| Gate | Result | Artifact |
| --- | --- | --- |
| URL smoke (801× HTTP **200**) | **801/801** pass | `parity/reports/url-parity/check-1793253267396.json` |
| `parity:head:refpack` | **21/21** meta | `parity/reports/head-meta/compare-1793253279952.json` |
| `parity:template-samples:http` | **18/18** all 200 | `parity/reports/template-samples-http/check-1793253280138.json` |
| `drush df-editor:test-publish-workflow` | pass | (CLI; no artifact) |

## Resume checklist

1. `git fetch origin cursor/phase4-chrome-behavior-0b67 && git checkout cursor/editor-followups-ce7f && git merge origin/cursor/phase4-chrome-behavior-0b67`
2. Bootstrap if needed: `bash scripts/repro/bootstrap-vm.sh`
3. `cd drupal/web && ../vendor/bin/drush cr && ../vendor/bin/drush df-editor:test-publish-workflow`
4. `cd parity && DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:url:check && npm run parity:head:refpack && npm run parity:template-samples:http`

Updated: 2026-10-29T05:55Z
