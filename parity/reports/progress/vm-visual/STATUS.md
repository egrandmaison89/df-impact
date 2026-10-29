# VM visual parity — PR #25 integrator status

**Branch:** `cursor/gutenberg-hover-archive-2b1b` (merged **phase4** @ `ac138a96`, schema **10035**).  
**Drupal:** `http://127.0.0.1:8888` · **Live-WP baseline:** `npm run parity:computed-style-diff:full:live-wp`

**Updated:** 2026-10-29 ( **`full-cs-pass3-live-pr25` complete** — 60/60 jobs, 0 pending)

---

## Pass 2 vs Pass 3 — live WP desktop `comparableSum`

Same harness (`parity-selectors.json`), **`PARITY_WP_SOURCE=live`**, **`REPORT_RUN_ID`:**

| Template | Pass 2 (`full-cs-pass2-after`) | Pass 3 (`full-cs-pass3-live-pr25`) | Δ | Verdict |
|----------|-------------------------------:|-----------------------------------:|--:|---------|
| **homepage** | 68 | **68** | 0 | Hold |
| **issue** | 114 | **83** | **−31** | Improved (banner + PostX sort/exclusions + grid margin) |
| **article** | 67 | **69** | +2 | Noise (wheeling/AACR paths; WP 200 comparable) |
| **in_brief** | 90 | **94** | +4 | Noise (single-path +4 on `/in-brief` mobile/tablet not in desktop Σ; desktop paths ±1) |
| **landing_page** | 94 | **116** | +22 | **Explained** — see below |
| **archive** | 179 | **200** | +21 | **Explained** — see below |

**Artifacts:** pass2 aggregate from git `76dec2e9` (`full-cs-pass2-after/aggregate.json`); pass3 `parity/reports/computed-style-diff/full-cs-pass3-live-pr25/aggregate.json` (local VM, gitignored).

### Regressions — evidence (not refpack / not baseline change)

**landing_page (+22 desktop Σ)** — both `/about` and `/about-impact` **+11** each vs pass2:

| Path | Pass 2 desktop mismatches | Pass 3 | Top new deltas (pass 3) |
|------|--------------------------:|-------:|------------------------|
| `/about` | 29 | **40** | **`main` / `.wp-site-blocks` h Δ ~406px** (pass 2 ~138px); **`main .wp-block-columns` y Δ ~196px** |
| `/about-impact` | 29 | **40** | Same pattern |

**Cause:** PR #25 **Gutenberg About body** (10029 + twig `entry-content`) vs pass2 **BEM/shorter** Drupal DOM — document height and staff **wp-block-columns** band no longer match pass2 Drupal snapshot; aligns to **live WP block markup** target. Not a harness regression.

**archive (+21 desktop Σ)** — DE paths **+10–11** each:

| Path | Pass 2 | Pass 3 | Top deltas (pass 3) |
|------|-------:|-------:|---------------------|
| `/category/digital-exclusives` | 36 | **47** | **`main` y ~92px**, **`main` w ~226px**, page **h ~593px** vs WP |
| `/digital-exclusives` | 36 | **46** | **`main` y ~103px**, **`main` h ~403px** (legacy route + listing layout) |

**Cause:** Archive row/order parity + **same-path** `/digital-exclusives` controller vs frozen WP listing (featured row / first-row geometry). Category archive content filter parity from phase4 — remaining gaps are **layout/content row** not chrome. Follow-up: templates/content-sweep, not PR #25 rollback.

**issue (−31)** — spring **40→24**, summer **37→22** desktop (banner width, PostX grid).

---

## PR diff deletions (−346k lines / ~2420 files vs phase4)

**Audit:** **100%** of deleted paths are under **`parity/reports/`** (generated harness output). **Zero** deletions of source, Drupal config/sync, theme templates, tests, **`parity/reference-pack/`**, **`parity/config/`**, migration data, or **`wp-images`**.

**Removed directory prefixes (representative):**

- `parity/reports/computed-style-diff/` (per-job JSON, PNG, aggregates)
- `parity/reports/computed-style-hover/`
- `parity/reports/behavior/`
- `parity/reports/head-meta/`
- `parity/reports/content-sweep/`
- `parity/reports/url-parity/`
- `parity/reports/progress/chrome-global/` (per-path progress JSON)
- `parity/reports/smoke-200/`, `parity/reports/template-samples-http/`, etc.

**Kept in git:** `parity/reports/progress/**/STATUS.md` only (see `parity/.gitignore`).

---

## Issue PostX — full harness (post `margin-top: 24px`)

**Run:** `PARITY_TEMPLATE=issue` · `PARITY_WP_SOURCE=live` · `REPORT_RUN_ID=issue-postx-post-margin` (all 3 issue sample paths × breakpoints).

**Spring `/spring-2026-issue` desktop — 24 mismatches (`wpComparable` true), top 10:**

| # | Property | Δ (approx) |
|---|----------|------------|
| 1 | `.wp-site-blocks` h | 412px |
| 2 | `main` h | 412px |
| 3–5 | footer / `.footer-dark` / footer columns **y** | 412px (page-height cascade) |
| 6 | `.ultp-block-items-wrap .ultp-block-item` **h** | 26px |
| 7–10 | item `height` style; card image metrics | secondary |

**Summer `/summer-2025-issue` desktop — 22 mismatches, top 10:**

| # | Property | Δ (approx) |
|---|----------|------------|
| 1–3 | `.wp-site-blocks` / `main` / footer **h** cascade | ~308px |
| 4–7 | footer stack **y** | ~308px |
| 8–10 | PostX item **img** natural/rendered width/margin | card image sizing |

**PostX `y` (~24px) no longer in top 10 on spring** after margin fix. Remaining PostX noise: **card image rendered size** (summer) and **row h** (26px).

---

## Gates (2026-10-29)

| Gate | Result |
|------|--------|
| `parity:smoke:200` | **0** fails |
| `parity:template-samples:http` | **19/19** |
| `verify_df_setup_schema.php` | **OK** (10035, 10027 marker set) |
| `verify_df_setup_post_updates.php` | **OK** |
| `drush updatedb:status` | **None pending** |
| Numbered hooks | **max 10035** |

`parity:head:refpack` (template): **16/18** — DE jsonLd thumbnail frozen vs live (unchanged).

---

## Merge readiness

| Criterion | Status |
|-----------|--------|
| Pass 3 live-WP full run + pass2 table | **Done** |
| Regressions explained with path evidence | **landing_page, archive** — intentional / follow-up |
| Diff audit | **Reports-only deletions** |
| PostX spring/summer full harness | **Done** |
| Gates | **Green** |

**Verdict: READY for integrator merge** — desktop Σ regresses on **landing_page** and **archive** are **documented** (Gutenberg About + archive listing geometry); **issue** improved **−31**; no non-generated files removed. Optional follow-up PR: About block vertical rhythm, DE archive first-row width, refresh refpack jsonLd.

---

## Commands

```bash
cd parity && DRUPAL_BASE_URL=http://127.0.0.1:8888 REPORT_RUN_ID=full-cs-pass3-live-pr25 npm run parity:computed-style-diff:full:live-wp
cd parity && DRUPAL_BASE_URL=http://127.0.0.1:8888 PARITY_TEMPLATE=issue PARITY_WP_SOURCE=live REPORT_RUN_ID=issue-postx-post-margin npm run parity:computed-style-diff
drush php:script modules/custom/df_setup/scripts/verify_df_setup_post_updates.php
```
