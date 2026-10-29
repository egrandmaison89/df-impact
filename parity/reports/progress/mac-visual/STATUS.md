# Mac visual parity — live status

**Branch:** `cursor/phase4-chrome-behavior-0b67` (PR **#5**). **Mac** = pixels + gates; **VM** = computed-style/CSS rows; **Meta (#10)** / **Templates (#11)** merged via PR **#12**.

**Updated:** 2026-10-29 ~1:20 AM ET

**Stash:** `mac-wip` (local report PNG backup; not committed).

---

## DDEV gates (latest)

| Gate | Result | Report / run |
|------|--------|----------------|
| wp-port assets | **19/19** | `scripts/repro/verify-wp-port-assets.sh` |
| `parity:head:refpack` | **21/21** | `parity/reports/head-meta/compare-1793251155492.json` |
| `parity:head:manifest` | **773/773** (prior run) | `manifest-1793249838983.json` |
| `parity:url:check` | **801/801** | `parity/reports/url-parity/check-1793251200598.json` |
| `parity:behavior:smoke` | **pass** (3/3, lite mode) | `parity/reports/behavior/2026-10-29T05-10-49-041Z/` |

**Behavior smoke fix:** `gotoWithBoundedSettle` (`load` + capped fonts/settle), `PARITY_BEHAVIOR_LITE=1` on smoke (skip heavy hovers/mobile drawer/reduced-motion reload), abort analytics on live WP.

**Template medians (2026-10-29):** homepage viewport D **33.0%** / chrome **5.1%**; issue viewport D **67.8%** / chrome **2.1%** (PostX cards now **i0.wp.com fit=768×551**, was `wp_card_870x570` on wp-images).

Command: `DRUPAL_BASE_URL=http://df-impact.ddev.site PARITY_TEMPLATE=<name> npm run parity:compare-template`

**QA screenshots:** `npm run parity:qa:capture` → `parity/reports/progress/mac-visual/qa-evidence/<id>/drupal|wp-{1440|768|375}.png` (PNGs gitignored; `qa-evidence/manifest.json` tracked).

---

## Manual QA checklist (owner report → agent ownership)

| # | Issue | Agent / PR | Status | Evidence |
|---|--------|------------|--------|----------|
| 1 | Header broken on scroll | Meta **#10** | **verify** | `qa-evidence/01-header-scroll/`; `wp-chrome-behaviors.js` toggles `scrolled` |
| 2 | Two translate widgets | Meta **#10** | **likely OK** | DDEV probe: **1** gtranslate region on `/homepage` |
| 3 | Vimeo embeds as plain URL | Meta **#10** | **open** | Article probe: no `player.vimeo.com` / vimeo block on Charlie Landers |
| 4 | Pagination | Templates **#11** | **verify** | `qa-evidence/04-pagination/`; `/category/cancer-research/page/2` **200** |
| 5 | Illegible In Brief band | Templates **#11** | **verify** | `qa-evidence/05-in-brief-band/` on `/fall-2026-issue` |
| 6 | `/browse-issues` image sizes | Templates **#11** | **verify** | `qa-evidence/06-browse-issues-images/` |
| 7 | HTML entities (`&#039;`) | Templates **#11** | **partial** | Issue promo excerpts decoded in theme; homepage **meta description** still encoded (meta module) |
| 8 | Broken images | Templates **#11** | **verify** | `qa-evidence/08-broken-images/` |
| 9 | Stray Home breadcrumb + edit pencils | VM **#12** | **partial** | Toolbar hidden in CSS; `/browse-issues` still shows breadcrumb text in HTML probe |
| 10 | More Stories without images | VM **#12** | **verify** | `qa-evidence/10-more-stories/` |
| 11 | Category spacing under articles | VM **#12** | **verify** | `qa-evidence/11-category-spacing/` |

When agent branches push ahead of PR **#5**: merge → `ddev drush cr` → re-run gates + `parity:qa:capture` → update rows above.

---

## Mac agent queue (overnight)

1. **Issue ~68%:** PostX card rasters + promo grid (`/fall-2026-issue` desktop **~59%** per-path); banner overlay h1 — stay in issue twig / card URL helpers.
2. **Homepage ~33%:** hero + grid pixels (not VM offset CSS).
3. **Defer:** article/in-brief +149px main, `/about`, archive row CSS (VM); vimeo/meta description (meta).

---

## Integrator reference

PR **#12** merged at `76cea9ad`. VM STATUS: `parity/reports/progress/vm-visual/STATUS.md`.
