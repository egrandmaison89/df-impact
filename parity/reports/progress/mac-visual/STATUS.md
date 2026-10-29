# Mac visual parity — live status

**Branch:** `cursor/phase4-chrome-behavior-0b67` (PR **#5**). **Mac agent** = pixel/template gates; **VM agent** (PR **#12**) = computed-style + article/in-brief/landing/archive rows.

**Updated:** 2026-10-29 ~1:10 AM ET (PR **#12** merge + DDEV re-gate)

**Local WIP:** `git stash list` → `mac-wip` (report PNG churn + any local overrides backup; not committed).

---

## Integrator: PR #12 merged (`76cea9ad`)

Merged `origin/cursor/vm-visual-parity-2b1b` (PR **#10** + **#11**, wp-images restore, `/about`+`/home` aliases, archive row `_1450` URLs, `computed-style-diff` harness). Large deletions = dropped ephemeral head/url JSON reports + gitignore (see VM `e11f080e` — wp-images **restored**, not lost).

VM header note: desktop `.desktop-header` **1440×148**, `.header-logo` **x=15** (content-box shell; no PR #10 alignfull bleed).

---

## DDEV gates (`http://df-impact.ddev.site`, post PR #12)

| Gate | Result | Report |
|------|--------|--------|
| **`verify-wp-port-assets.sh`** | **19/19 OK** | — |
| **`parity:head:refpack`** | **21/21** metaPass | `parity/reports/head-meta/compare-1793249788015.json` |
| **`parity:head:manifest`** | **773/773** metaPass | `parity/reports/head-meta/manifest-1793249838983.json` |
| **`parity:url:check`** (`URL_PARITY_CONCURRENCY=1`) | **801/801** | `parity/reports/url-parity/check-1793249844205.json` |
| **`parity:behavior:smoke`** | **fail** (3× `/homepage` Playwright **240s timeout** tablet/mobile; desktop **hero poster** mismatch) | `parity/reports/behavior/2026-10-29T04-56-23-515Z/` |

Pipeline: `ddev composer install` → `ddev drush updb -y && ddev drush cr` → npm scripts above.

**Behavior follow-up (Mac):** removed `poster=` on homepage hero `<video>` (WP `v.poster` empty); re-run smoke after `drush cr`.

---

## Template medians — before / after PR #12 (Mac pack vs DDEV)

| Template | Viewport D (before → after) | Chrome D (before → after) |
|----------|---------------------------|---------------------------|
| **homepage** | 33.0% → **33.0%** | 5.1% → **5.1%** |
| **issue** | 67.8% → **67.8%** | 3.6% → **3.6%** |
| **article** | 32.0% → **32.0%** | 5.0% → **5.1%** |
| **in_brief** | 20.2% → **20.2%** | ~5% → **5.0%** |
| **landing_page** | 34.8% → **34.8%** | 5.1% → **5.0%** |
| **archive** | 30.5% → **30.4%** | ~5% → **5.2%** |

Full D/T/M medians (after):

| Template | Viewport D | Viewport T | Viewport M | Chrome D | Chrome T | Chrome M |
|----------|----------:|-----------:|-----------:|---------:|---------:|---------:|
| homepage | 33.0% | 33.7% | 32.4% | 5.1% | 16.1% | 17.8% |
| issue | 67.8% | 65.3% | 62.6% | 3.6% | 12.8% | 13.0% |
| article | 32.1% | 35.2% | 27.8% | 5.1% | 14.6% | 15.3% |
| in_brief | 20.2% | 12.3% | 16.9% | 5.0% | 15.1% | 15.2% |
| landing_page | 34.8% | 39.1% | 36.0% | 5.0% | 14.1% | 15.3% |
| archive | 30.4% | 53.8% | 35.2% | 5.2% | 14.0% | 15.2% |

Command: `DRUPAL_BASE_URL=http://df-impact.ddev.site PARITY_TEMPLATE=<name> npm run parity:compare-template`

---

## Mac agent queue (do not collide with VM)

1. **Issue ~68%:** PostX card raster / promo grid pixels (`df_impact.theme` card URLs, issue twig — **not** `drupal-overrides.css`).
2. **Homepage:** hero behavior + viewport grids (VM owns offset/CSS elsewhere).
3. **Defer to VM/templates agents:** article/in-brief **main y +149px**, `/about` Gutenberg, archive row layout, `theme.css` global tweaks.

---

## Prior DDEV gates (pre PR #12, for diff)

| Gate | Result |
|------|--------|
| head:refpack | 21/21 (`compare-1793244596979.json`) |
| head:manifest | 773/773 (`manifest-1793244662018.json`) |
| url:check | 801/801 (`check-1793244664143.json`) |
