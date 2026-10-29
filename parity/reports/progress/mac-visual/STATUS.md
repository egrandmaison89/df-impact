# Mac visual parity — live status

**Branch:** `cursor/phase4-chrome-behavior-0b67` (PR **#5**). **Mac** = pixels + gates; merged **Meta #10**, **Templates #13**, **VM #14**, **Editor #15** (`cursor/editor-workflow-ce7f`).

**Updated:** 2026-10-29 ~1:50 AM ET

**Stash:** `mac-wip` (local report PNG backup; not committed).

---

## DDEV gates (post PR #14 merge)

| Gate | Result | Report / run |
|------|--------|----------------|
| Homepage + listing HTTP | **200** | `/homepage`, `/category/cancer-research` |
| wp-port assets | **19/19** | `scripts/repro/verify-wp-port-assets.sh` |
| `parity:head:refpack` | **21/21** | `parity/reports/head-meta/compare-1793252268994.json` |
| `parity:head:manifest` | **773/773** | `parity/reports/head-meta/manifest-1793252306925.json` |
| `parity:url:check` | **801/801** | `parity/reports/url-parity/check-1793252311324.json` |
| `parity:behavior:smoke` | **pass** (3/3, lite) | `parity/reports/behavior/2026-10-29T05-38-55-038Z/` |

**PR #14 fixes:** `is_array($variables['row'])` guard (no ResultRow 500); removed `.desktop-header.scrolled` / `.impact-header.scrolled` width overrides; digital-exclusives refpack meta baseline in `compare-head-meta.mjs`. **Mac add-on:** `header--scrolled` alignfull viewport breakout only (no `.scrolled` width rules).

**Full behavior:** run `npm run parity:behavior:templates` (no `PARITY_BEHAVIOR_LITE`) periodically for hovers / mobile drawer.

**QA screenshots:** `npm run parity:qa:capture` → `parity/reports/progress/mac-visual/qa-evidence/<id>/` (PNGs gitignored; `qa-evidence/manifest.json` tracked). Last capture: `manifest.json` → `2026-10-29T05:41:02.576Z`.

---

## Manual QA checklist (11 items)

| # | Issue | Agent / PR | Status | Evidence |
|---|--------|------------|--------|----------|
| 1 | Header broken on scroll | Meta **#10** / VM **#14** | **pass** | `qa-evidence/01-header-scroll/`; behavior smoke 0 mismatches (`headerLayout600/2000`); shadow on `.header--scrolled` only |
| 2 | Two translate widgets | Meta **#10** | **pass** | `qa-evidence/02-translate-widget/`; CSS hides mobile wrapper ≥769px and desktop wrapper ≤768px (two nodes in DOM, one visible per breakpoint) |
| 3 | Vimeo embeds as plain URL | Meta **#10** | **partial** | QA clip path Charlie Landers may be link-only; **`player.vimeo.com`** on `/2023/11/dismantling-gender-disparity-through-mentorship` (nid 293) after backfill — `qa-evidence/03-vimeo-embed/` |
| 4 | Pagination | Templates **#13** | **pass** | `qa-evidence/04-pagination/`; `/category/cancer-research/page/2` **200** + `wp-block-query-pagination` markup |
| 5 | Illegible In Brief band | Templates **#13** | **verify** | `qa-evidence/05-in-brief-band/` on `/fall-2026-issue` (screenshot compare) |
| 6 | `/browse-issues` image sizes | Templates **#13** | **verify** | `qa-evidence/06-browse-issues-images/` |
| 7 | HTML entities (`&#039;`) | Templates **#13** | **pass** | `/fall-2026-issue` probe: **0** `&#039;` after `decode_text_entities.php`; homepage meta description may still encode (meta module) |
| 8 | Broken images | Templates **#13** | **verify** | `qa-evidence/08-broken-images/` |
| 9 | Stray Home breadcrumb + edit pencils | VM **#12** / **#14** | **partial** | Toolbar/contextual hidden in CSS; `#block-df-impact-breadcrumbs` still in browse-issues HTML — `qa-evidence/09-breadcrumb-edit/` |
| 10 | More Stories without images | VM **#12** | **verify** | `qa-evidence/10-more-stories/` |
| 11 | Category spacing under articles | VM **#12** | **verify** | `qa-evidence/11-category-spacing/` |

**Also:** `/digital-exclusives` loads **200** (VM route); refpack meta **21/21** with category archive baseline for DE slug.

---

## Mac agent queue

1. **Issue viewport ~68%:** PostX photon cards, promo/banner pixels on `/fall-2026-issue`.
2. **Homepage ~33%:** hero + grid (not VM offset CSS).
3. **Vimeo QA:** point checklist capture at an iframe article (e.g. dismantling-gender…) or re-run backfill on remaining link-only bodies.

VM STATUS: `parity/reports/progress/vm-visual/STATUS.md`.
