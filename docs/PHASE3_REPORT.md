# Phase 3 report — content, archive IA, chrome, meta

## 1. Content completeness (WXR → migration JSON)

**Source:** `drupal/migration-data/private/wp-export.xml` (gitignored).

**Pipeline:**

```bash
cd drupal/migration-data
python3 process_wxr_data.py   # → articles, in_brief, issues, taxonomies, media copy
```

**Decisions recorded:**

| Topic | Decision |
| --- | --- |
| Post dates | WXR uses `Y-m-d H:i:s`; migration expects ISO → normalized to `Y-m-d\TH:i:s` in `process_wxr_data.py`. |
| Issue tags | WXR tag `count` is 0 → recomputed from post tag IDs before `process_wp_data.py`. |
| Newest issues | WXR issue **pages** (`/{season}-{year}-issue`) merged into `issues.json` with `path_alias` when tag-derived issues were missing. |
| In Brief vs article | Same rules as REST pipeline (`In Brief {Season} {Year}` tags). |
| Permalinks | `drupal_alias` from live permalink path (unchanged from Phase 2). |

**Reconcile** (`reconcile_wxr_drupal.py`, 783 published posts + pages):

| Metric | Phase 2 | Phase 3 |
| --- | ---: | ---: |
| Matched paths | 664 | **781** |
| Missing | 119 | **2** (`/pagination-test`, `/video-test` — WP QA pages, intentionally omitted) |

**Drupal nodes (published):** 436 articles, 323 in brief, 16 issues.

## 2. Archive & landing IA

| WordPress path | Drupal |
| --- | --- |
| `/issues` (Stories Archive) | **New** `stories_archive` view → article teasers, 12/page |
| `/browse-issues` | `browse_issues` view path corrected from `issues` → **`browse-issues`** |
| `/past-issues` | **New** `past_issues` view → issue teasers |
| `/in-brief` | Existing `in_brief_listing` view (unchanged) |
| `/`, `/homepage` | Front page; route normalizer bypass for **both** paths |

## 3. Global chrome & meta

- Header/footer tokens documented in `wp_design_tokens.json`; header vertical padding aligned to WXR **20px**.
- **Metatag:** node defaults use `[node:field_meta_description]` (from WXR Yoast) with fallback to summary for description / OG / Twitter.

**Parity evaluation:** Run locally with:

```bash
cd parity
npm run parity:smoke          # live WP (captcha may affect WP side)
PARITY_WP_BASELINE=<id> npm run parity:smoke   # after owner Mac capture
```

Report chrome before/after from smoke JSON under `parity/results/` once baseline is available.

## Owner follow-ups

1. **Frozen WP baseline** on Mac → commit under `parity/baselines/<id>/` per [FROZEN_WP_BASELINE.md](./FROZEN_WP_BASELINE.md).
2. Optional: drop QA pages into Drupal only if parity manifest should include them.
3. Re-run full `npm run parity` after baseline for release gating.
