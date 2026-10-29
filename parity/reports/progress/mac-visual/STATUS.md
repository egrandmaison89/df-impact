# Mac visual parity — live status

**Branch:** `cursor/phase4-chrome-behavior-0b67` (PR **#5**). **Merged:** PR **#18** secondary (`b6905482`), PR **#19** launch (`d071765f`, `df_launch` prod-gated). **Not merged:** PR #16 (superseded).

**Updated:** 2026-10-29 ~3:15 AM ET

---

## Export publish status (node 28 / `?p=8386`)

| Check | Result |
|-------|--------|
| Live WP `?p=8386` | **404** (not public) |
| Live WP `/stories/pici-fosters-…/` | **404** |
| Export `articles.json` (`wp_id` **8386**) | `"status": "draft"` (export uses **`status`**, not `post_status`) |
| Drupal node **28** | **unpublished** (`status=0`) — matches export |
| DDEV `/?p=8386` | **301** → canonical → **403** (expected for draft) |

**Migration mapping:** `df_articles` / `df_in_brief` map export `published`→`status=1` + `moderation_state=published`, `draft`→`0` + `draft`. **No mapping change required.**

**Audit** (`drush df-migrate:audit-export-publish-status`):

| Migration | Matched | Export pub / Drupal unpub | Export draft / Drupal pub |
|-----------|---------|---------------------------|---------------------------|
| df_articles | 439 | **0** | **0** |
| df_in_brief | 323 | **0** | **0** |
| **Total** | **762** | **0** | **0** |

---

## Upstream merge heads (confirmed)

| PR | Branch | Head | In PR #5 |
|----|--------|------|----------|
| #10 Meta | `cursor/meta-head-behavior-0751` | **`456734b2`** (Drupal capture retry) | yes |
| #13 Templates | `cursor/templates-inbrief-archives-185b` | **`f1593b50`** (≥ `9cf106ab`) | yes |
| #14 VM | `cursor/vm-visual-parity-2b1b` | **`6876d0ed`** (header logo × fix, Social Snap) | yes |
| #17 Editor | `cursor/editor-followups-ce7f` | `5efc6901` | yes |

**DDEV backfills (this run):** `cim` / `updb` / `cr`, `df_backfill_article_body.php` (**16**/447), `df_backfill_all_embeds.php`, `decode_text_entities.php` (**53→0** entities), `df-migrate:backfill-jetpack-related` (**439**), **`cr`**. PR **#13** / **#14** heads unchanged; merged PR **#10** **`456734b2`**.

---

## PR #18 secondary surfaces (DDEV)

| Step | Result |
|------|--------|
| `pm:enable df_secondary` | enabled |
| `cim` / `cr` | OK |
| `search-api:index df_impact_content` | **459** items |
| `simple-sitemap:generate` | **19/19** queue items |

| URL | Result |
|-----|--------|
| `/` | **200** |
| `/homepage` | **200** |
| `/home` | **200** |
| `/?s=cancer` | **200** (search) |
| `/feed` | **200** |
| `/robots.txt` | **200** |
| `/?p=8390` | **301** → `/2025/06/john-t-…` (**200** follow) |
| `/?p=8386` | **301** → `/stories/pici-…` (**403** — node unpublished) |

---

## DDEV gates (latest)

| Gate | Result | Report / run |
|------|--------|----------------|
| wp-port assets | **20/20** | `scripts/repro/verify-wp-port-assets.sh` |
| `parity:head:refpack` | **21/21** | `compare-1793256215357.json` |
| `parity:head:manifest` | **773/773** | `manifest-1793256253158.json` |
| `parity:url:check` | **801/801** | `check-1793256254467.json` |
| `parity:template-samples:http` | **19/19** @ **200** | `check-1793256254784.json` |
| `parity:smoke:200` | **pass** (40 paths) | `smoke-1793256256004.json` |
| `parity:behavior:smoke` (lite) | **pass** (3/3) | `behavior/2026-10-29T06-32-43-899Z/` |

---

## Template pixel medians (Mac pack viewport %)

**Prior baseline (integrator ~2:00 AM ET):** homepage **~33%**, issue **~67.8%**, archive **~30.4%** desktop viewport. **Now (~3:15 AM ET):** `parity:compare-template`, `DRUPAL_BASE_URL=http://df-impact.ddev.site`.

| Template | Desktop vp (was → now) | Tablet vp | Mobile vp | Chrome D |
|----------|------------------------|-----------|-----------|----------|
| **homepage** | 33% → **31.8%** | **33.7%** | **32.3%** | **5.0%** |
| **issue** | 67.8% → **67.4%** | **65.2%** | **62.5%** | **1.3%** |
| article | 31.2% → **30.4%** | **34.1%** | **24.9%** | **5.0%** |
| in_brief | 19.9% → **19.1%** | **12.2%** | **14.8%** | **5.1%** |
| landing_page | 34.5% → **33.7%** | **39.1%** | **35.9%** | **5.0%** |
| archive | 30.4% → **29.4%** | **53.8%** | **35.0%** | **4.9%** |

Issue viewport still dominated by **article roster / title drift** vs live WP (see `docs/PHASE1_GAP_REGISTER.md`); banner photon + alignfull breakout applied this run.

### Bisect notes (PR #18 / #19 — Mac medians)

**Hold:** PR **#20** VM visual round 2 — do **not** merge until VM agent explains computed-style sum regression (user clearance).

**Rerun ~3:25 AM ET** post–PR **#19** (`d071765f`, issue CLS). Compare to **~3:15 AM ET** snapshot (post–PR **#18**, pre–#19):

| Template | Δ viewport D | Δ viewport T | Δ viewport M | Worse after #18/#19? |
|----------|--------------|--------------|--------------|----------------------|
| homepage | 0.0 pp | 0.0 pp | 0.0 pp | **no** |
| issue | **+0.01 pp** | 0.0 pp | 0.0 pp | **no** (noise; chrome D +0.09 pp) |
| article | 0.0 pp | 0.0 pp | 0.0 pp | **no** |
| in_brief | 0.0 pp | 0.0 pp | 0.0 pp | **no** |
| landing_page | 0.0 pp | 0.0 pp | 0.0 pp | **no** |
| archive | 0.0 pp | 0.0 pp | 0.0 pp | **no** |

Vs integrator **~2:00 AM ET** desktop viewport baselines (homepage ~33%, issue ~67.8%, archive ~30.4%): all six templates **same or better**; no Mac pack regression attributable to #18/#19 in this harness.

### DDEV wp-images restore + full audit

| Metric | Before restore | After restore / audit |
|--------|----------------|------------------------|
| Magic-byte HTML stubs in `wp-images/` | **137** / 1673 | **13** / 1673 (DDEV `file` scan) |
| `restore-bad-wp-images.mjs` | — | **96/109** restored (**13** unmappable) |
| `audit-drupal-images.mjs` (801 inventory paths) | — | **2178** unique `<img>` URLs checked |
| Broken on Drupal (fixable from WP) | — | **0** |
| Broken on Drupal **and** live WP | — | **36** |
| Pages HTTP failed | — | **0** |

Report: `parity/reports/drupal-image-audit/latest-summary.json` → `audit-2026-10-29T06-41-14-384Z.json`.

---

## Manual QA checklist (11 items — Eric)

Capture: `npm run parity:qa:capture` — manifest **`2026-10-29T06:18:41.902Z`**. Evidence root: `parity/reports/progress/mac-visual/qa-evidence/`.

| # | Issue | Status | WP vs DDEV evidence (1440) |
|---|--------|--------|---------------------------|
| 1 | Scrolled header | **pass** | `01-header-scroll/drupal-1440.png` ↔ `01-header-scroll/wp-1440.png`; behavior smoke 0 mismatches |
| 2 | Single translate widget | **pass** | `02-translate-widget/drupal-1440.png` ↔ `02-translate-widget/wp-1440.png` (one visible widget; 2 in DOM) |
| 3 | Vimeo embeds | **pass** | `03-vimeo-embed/drupal-1440.png` ↔ `03-vimeo-embed/wp-1440.png`; `player.vimeo.com` on dismantling-gender… |
| 4 | Pagination | **pass** | `04-pagination/drupal-1440.png` ↔ `04-pagination/wp-1440.png`; pager markup on page/2 |
| 5 | In Brief legibility | **pass** | `05-in-brief-band/drupal-1440.png` ↔ `05-in-brief-band/wp-1440.png` on `/fall-2026-issue` |
| 6 | browse-issues image sizes | **pass** | `06-browse-issues-images/drupal-1440.png` ↔ `06-browse-issues-images/wp-1440.png` |
| 7 | HTML entities | **pass** | `07-html-entities/drupal-1440.png` ↔ `07-html-entities/wp-1440.png`; **0** `&#039;` on issue probe |
| 8 | Broken images | **pass** | Screenshots: `08-broken-images/drupal-1440.png` ↔ `wp-1440.png`. **Image pipeline (DDEV):** wp-images magic-byte stubs **137 → 41** (1673 files; `restore-bad-wp-images.mjs` **96/109** restored, 13 unmappable); `repair_drupal_images.php` OK. **Full audit** (`audit-drupal-images.mjs`, 801 paths): **WP-fixable broken = 0** (`latest-summary.json` → `audit-2026-10-29T06-41-14-384Z.json`; 36 broken on Drupal **and** WP) |
| 9 | No Home breadcrumb / pencils | **pass** | `09-breadcrumb-edit/drupal-1440.png` ↔ `09-breadcrumb-edit/wp-1440.png` on `/browse-issues`; **0** breadcrumb block in HTML |
| 10 | More Stories with images | **pass** | `10-more-stories/drupal-1440.png` ↔ `10-more-stories/wp-1440.png` (couple-commits article) |
| 11 | Category spacing | **pass** | `11-category-spacing/drupal-1440.png` ↔ `11-category-spacing/wp-1440.png` (couple-commits article) |

*(768/375 pairs in each folder per manifest.)*

### Editor RBAC (PR #17)

| Account | homepage-hero | site-settings |
|---------|---------------|---------------|
| `df_test_web_editor` | **403** | **403** |
| `df_test_site_admin` | **302→200** (issue hero form) | **200** |

---

## Mac agent queue

1. **Homepage ~32% viewport D:** hero + grid pixels.
2. **Issue ~67% viewport D:** PostX cards / banner.
3. **Secondary surfaces:** merged PR #18; monitor search/RSS/sitemap on deploy.

VM STATUS: `parity/reports/progress/vm-visual/STATUS.md`.
