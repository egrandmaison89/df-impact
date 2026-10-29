# Editor workflow agent status

**PR:** https://github.com/egrandmaison89/df-impact/pull/17  
**Branch:** `cursor/editor-followups-ce7f`

## PHPUnit (VM, 2026-10-29)

Install dev deps once: `bash scripts/repro/install-phpunit-deps.sh`

```bash
cd drupal/web/modules/custom/df_editor && ../../../../vendor/bin/phpunit -c phpunit.xml.dist
# OK (6 tests, 36 assertions) — publish workflow, editor_html filters, migrated full_html save
```

## Editor HTML security (PR #17)

| Item | Status |
| --- | --- |
| **`editor_html` text format** | CKEditor 5 + `filter_html` allowlist for WP block patterns |
| **Custom filters** | `editor_html_sanitize` (script + `on*`), `editor_html_iframe` (Vimeo/YouTube hosts only) |
| **Web editor role** | `use text format editor_html` + legacy permission to edit stored `full_html` bodies |
| **Site admin** | `use text format full_html` (only role with unrestricted Full HTML) |
| **Migrated articles** | Body format stays `full_html`; web editor save parity tested (2 articles) |

## Editor UX (PR #17 follow-up)

| Feature | Status |
| --- | --- |
| **Issue dashboard** (`/admin/content/issue-dashboard`) | Draft issue + attached articles table, publish confirm, preview links |
| **Homepage / Issues nav preview** | `?df_preview_issue=NID` + session; `df_setup_get_latest_issue_nid()` + header links honor preview |
| **Media alt required** | Image media form widgets |
| **Pattern render test** | `drush df-editor:test-article-patterns` (editor_html + script strip + migrated save) |
| **Guide screenshots** | `docs/editors/screenshots/*.png` |

## Parity gates (VM `http://127.0.0.1:8888`, 2026-10-29)

| Gate | Result | Artifact |
| --- | --- | --- |
| `parity:url:check` | **801/801** | `check-1793254008925.json` |
| `parity:head:refpack` | **21/21** | (see npm output) |
| `parity:template-samples:http` | **18/18** | (see npm output) |
| `drush df-editor:test-publish-workflow` | pass | |
| `drush df-editor:test-article-patterns` | pass | |

Updated: 2026-10-29T06:15Z
