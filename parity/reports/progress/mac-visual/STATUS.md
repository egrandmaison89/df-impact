# Mac visual parity — live status

**Branch:** `cursor/phase4-chrome-behavior-0b67` (PR **#5**). Integrator merges: **Meta #10**, **Templates #13**, **VM #14**, **Editor #15**, **Editor follow-ups #17** (`5efc6901` on `cursor/editor-followups-ce7f`). **Not merged:** PR #16 (superseded by #17).

**Updated:** 2026-10-29 ~2:16 AM ET

**Stash:** `mac-wip` (local report PNG backup; not committed).

---

## Upstream merge heads (confirmed)

| PR | Branch | Head | In PR #5 |
|----|--------|------|----------|
| #10 Meta | `cursor/meta-head-behavior-0751` | `2778e304` | yes |
| #13 Templates | `cursor/templates-inbrief-archives-185b` | `f780eb3c` | yes |
| #14 VM | `cursor/vm-visual-parity-2b1b` | `9f1e8b49` | yes |

**Post-merge DDEV:** `drush cim`, `updb`, `cr`; `php:script` `df_backfill_article_body.php` (0/439 new), `decode_text_entities.php` (entities **113→0**); `df-migrate:backfill-jetpack-related` (**439** updated). **Fix:** `DfMigrateCommands.php` parse error (`dt()` paren) so Drush commands register.

---

## DDEV gates (post PR #17)

| Gate | Result | Report / run |
|------|--------|----------------|
| `drush cim` / `cr` | **ok** | `editor_html` format + role updates imported |
| `drush df-editor:test-publish-workflow` | **pass** | After removing stale `DF Test Issue Workflow` nodes on DDEV |
| wp-port assets | **19/19** | `scripts/repro/verify-wp-port-assets.sh` |
| `parity:head:refpack` | **21/21** | `parity/reports/head-meta/compare-1793254525998.json` |
| `parity:head:manifest` | **773/773** | `parity/reports/head-meta/manifest-1793254566078.json` |
| `parity:url:check` | **801/801** | `parity/reports/url-parity/check-1793254567439.json` |
| `parity:template-samples:http` | **18/18** (all **200**) | `parity/reports/template-samples-http/check-1793254567745.json` |
| `parity:smoke:200` | **pass** (39 paths, 0 fails) | `parity/reports/smoke-200/smoke-1793254568956.json` |
| `parity:behavior:smoke` | **pass** (3/3, lite, prior) | `parity/reports/behavior/2026-10-29T06-00-24-489Z/` |

---

## Template pixel medians (Mac pack, desktop viewport D)

| Template | Before viewport D | After viewport D | Before chrome D | After chrome D |
|----------|-------------------|------------------|-----------------|----------------|
| **homepage** | 33.0% | **32.7%** | 5.1% | **14.3%** |
| **issue** | 67.8% | **68.2%** | 2.1% | **11.0%** |
| article | — | **31.2%** | — | **14.1%** |
| in_brief | — | **19.9%** | — | **14.1%** |
| landing_page | — | **34.5%** | — | **14.2%** |
| archive | — | **30.2%** | — | **14.4%** |

Before = pre–PR #10/#13/#14 refresh (~1:48 AM ET). After = post-merge `parity:compare-template` run 2026-10-29 ~2:00 AM ET (`DRUPAL_BASE_URL=http://df-impact.ddev.site`). Chrome medians rose (shared header/CSS drift); Mac queue remains homepage + issue viewport pixels.

---

## Manual QA checklist (11 items — Eric)

Evidence: `npm run parity:qa:capture` → `qa-evidence/<id>/drupal|wp-{1440|768|375}.png` (manifest `2026-10-29` post-merge).

| # | Issue | Status | Evidence |
|---|--------|--------|----------|
| 1 | Scrolled header | **pass** | `01-header-scroll/`; behavior smoke 0 mismatches; `.header--scrolled` + viewport breakout |
| 2 | Single translate widget | **pass** | `02-translate-widget/`; one visible `.gtranslate_wrapper` per breakpoint (CSS) |
| 3 | Vimeo embeds (not plain URL only) | **pass** | `03-vimeo-embed/` on dismantling-gender…; `player.vimeo.com` in HTML |
| 4 | Pagination | **pass** | `04-pagination/`; `/category/cancer-research/page/2` **200** + pager markup |
| 5 | In Brief legibility | **pass** | `05-in-brief-band/` on `/fall-2026-issue` (visual vs WP screenshots) |
| 6 | browse-issues image dimensions | **pass** | `06-browse-issues-images/` vs WP |
| 7 | HTML entities | **pass** | `07-html-entities/`; `/fall-2026-issue` **0** `&#039;`; decode script run |
| 8 | Broken images | **pass** | `08-broken-images/` homepage grid vs WP (no obvious broken `<img>` in capture) |
| 9 | No Home breadcrumb / edit pencils | **pass** | `09-breadcrumb-edit/` on `/browse-issues`; **0** `#block-df-impact-breadcrumbs` in HTML probe |
| 10 | More Stories with images | **pass** | `10-more-stories/` on couple-commits article; Jetpack grid markup present |
| 11 | Category spacing under articles | **pass** | `11-category-spacing/` on couple-commits article vs WP |

### Editor RBAC (PR #17 — DDEV, password `test`)

Logged-in HTTP subrequest (`http_kernel` as user):

| Account | `/admin/config/df-impact/homepage-hero` | `/admin/config/df-impact/site-settings` |
|---------|----------------------------------------|-------------------------------------------|
| `df_test_web_editor` | **403** (denied) | **403** (denied) |
| `df_test_site_admin` | **302** → issue edit `#edit-df-homepage-hero` (**200**) | **200** |

---

## Mac agent queue

1. **Homepage ~33% viewport D:** hero video/grid (chrome median regression ~14% — investigate shared header CSS).
2. **Issue ~68% viewport D:** PostX cards / banner (`Fall26-Banner-1024x396.jpg` vs photon).
3. **Full behavior:** `npm run parity:behavior:templates` periodically (hovers / mobile drawer).

Editor accounts on DDEV: `df_test_web_editor` / `df_test_site_admin` (password `test`). Site admins use **Impact site settings** and **Homepage hero** (redirects to current issue hero fields).

VM STATUS: `parity/reports/progress/vm-visual/STATUS.md`.
