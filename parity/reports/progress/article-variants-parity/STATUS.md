# Article variants + search + 404 parity

Branch: `cursor/article-variants-parity-185b` → base `cursor/phase4-chrome-behavior-0b67`.

## Search (`/?s=` → `/search`)

| Query | WP fixture / live | Drupal `/search?s=` | Structure pass |
| --- | --- | --- | --- |
| `cancer` | 10 `wp-block-post` rows, H1 “Search Results”, pager | 10 rows, excerpts, pager | yes |
| `jimmy` | 10 rows | 10 rows | yes |
| `fund` | 10 rows | 10 rows | yes |
| `zzznomatch99` | 0 rows, empty query main | 0 rows, empty main | yes |

Fixes: internal rewrite `/search` → Search API view (`search-legacy` route); Better Search–style templates (`wp-block-post-template`, column cards, `bsearch_highlight`); re-import `views.view.df_impact_search` so exposed fulltext filter applies; WP HTML fixtures under `parity/cache/wp-search/`.

Harness: `npm run parity:search:fetch-wp`, `npm run parity:search:compare` → `search-parity-*.json`.

## 404

Drupal `page--404.html.twig` matches WP copy (“Page not found”, search prompt). Hide duplicate Drupal page-title block on `system.404`.

## Article / In Brief variants (759 published nodes, 10 variant keys)

Classification: `variant-classification.json` (`npm run parity:article-variants:classify`).

| Variant | Count | Sample paths (2 each) |
| --- | ---: | --- |
| hero | 436 | `/2022/11/back-in-the-saddle-again-2022-pmc-raises-record-69-million-thats-900-million-since-1980`, `/2022/11/back-on-course-with-the-boston-marathon-jimmy-fund-walk` |
| no_hero | 323 | `/2023/01/boston-red-sox-jimmy-fund-award`, `/2023/01/breast-cancer-alliance` |
| sidebar | 433 | same as hero samples (alignright / column patterns) |
| pull_quote | 32 | `/2022/11/couple-commits-2-million-to-fund-groundbreaking-clinical-trials`, … |
| gallery | 32 | `/2022/11/back-on-course-with-the-boston-marathon-jimmy-fund-walk`, … |
| video_embed | 15 | `/2023/01/dana-farber-joins-with-parker-institute-for-cancer-immunotherapy-to-accelerate-cures-for-the-worlds-gravest-cancers`, … |
| long_form | 2 | `/2022/11/richard-and-susan-smith-family-receives-institutes-highest-honor-the-sidney-farber-medical-research-award`, `/2024/09/campaign-leadership-spurs-innovation-and-progress-at-dana-farber` |
| custom_blocks | 4 | `/2024/03/jimmy-fund-golf-community-helps-to-usher-in-the-jimmy-funds-75th`, `/2025/03/another-ace-year-for-jimmy-fund-golf` |
| year_2022_2023 | 176 | (see JSON) |
| year_2024_2026 | 583 | (see JSON) |

### Sample scores (after this branch)

Template article paths (text vs live WP, desktop):

| Path | Text (before*) | Text (after) | Notes |
| --- | --- | --- | --- |
| `/2022/11/couple-commits-2-million-to-fund-groundbreaking-clinical-trials` | ~0.98 ref | ≥0.98 | pull_quote + hero |
| `/2026/09/american-association-for-cancer-research-2` | in_brief parity | ≥0.98 | in_brief full |
| `/2024/09/wheeling-in-the-years-the-pan-mass-challenge-produces-origin-story-video` | video embed | ≥0.98 | video embed |

\*Before: Mac/ref-pack baseline from templates-inbrief-archives STATUS; after: live WP text harness (`sample-text-parity` / variant samples).

Run full variant sweep: `npm run parity:article-variants:samples` (live WP; long).

## Gates (`DRUPAL_BASE_URL=http://127.0.0.1:8888`)

| Gate | Result | Artifact |
| --- | --- | --- |
| `parity:smoke:200` | pass (0×5xx) | `smoke-179326487*.json` |
| `parity:template-samples:http` | 17/19 ( `/`, `/homepage` 404 — missing node/795 in VM DB; not introduced by this branch) | `check-1793264872464.json` |
