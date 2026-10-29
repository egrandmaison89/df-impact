# Editor workflow agent status

**PR:** https://github.com/egrandmaison89/df-impact/pull/17  
**Branch:** `cursor/editor-followups-ce7f`

## PHPUnit (VM, 2026-10-29)

Install dev deps once: `bash scripts/repro/install-phpunit-deps.sh`

```bash
cd drupal/web/modules/custom/df_editor && ../../../../vendor/bin/phpunit -c phpunit.xml.dist
# OK (2 tests, 21 assertions) — ExistingSite publish workflow + hero field access
```

## Editor UX (PR #17 follow-up)

| Feature | Status |
| --- | --- |
| **Issue dashboard** (`/admin/content/issue-dashboard`) | Draft issue + attached articles table, publish confirm, preview links |
| **Homepage / Issues nav preview** | `?df_preview_issue=NID` + session; `df_setup_get_latest_issue_nid()` + header links honor preview |
| **Full HTML body** for web editors | Role permission + article form default format |
| **Media alt required** | Image media form widgets |
| **Pattern render test** | `drush df-editor:test-article-patterns` |
| **Guide screenshots** | `docs/editors/screenshots/*.png` |

## Parity gates (VM `http://127.0.0.1:8888`, 2026-10-29)

| Gate | Result | Artifact |
| --- | --- | --- |
| `parity:url:check` | **801/801** | `check-1793253668022.json` |
| `parity:head:refpack` | **21/21** | `compare-1793253676374.json` |
| `parity:template-samples:http` | **18/18** | `check-1793253676558.json` |
| `drush df-editor:test-publish-workflow` | pass | |
| `drush df-editor:test-article-patterns` | pass | |

Updated: 2026-10-29T06:02Z
