# Phase 1 gap register (auto-generated)

Generated: 2026-10-28 15:03 UTC
Parity run: `2026-10-28T15-00-23-422Z`

## Headline scores (desktop)

```json
{
  "pixelDiffMedianDesktop": 0.16353523227963868,
  "textSimilarityMedianDesktop": 0.03007518796992481,
  "drupalReachableDesktop": 12,
  "wpReachableDesktop": 14
}
```

## Ranked gaps (by shared component)

| Severity | Component | Pages (sampled) | Avg pixel diff | Avg text similarity | Evidence | Proposed fix |
| --- | --- | ---: | ---: | ---: | --- | --- |
| critical | article template | 8 | 17.8% | 35.0% | `/2022/11/back-in-the-saddle-again-2022-pmc-raises-record-69-million-thats-900-million-since-1980`, `/2022/11/back-on-course-with-the-boston-marathon-jimmy-fund-walk`, `/2022/11/couple-commits-2-million-to-fund-groundbreaking-clinical-trials`; see `parity/reports/latest-report.html` | Body format filters, byline/credit fields, featured image styles, related issue link. |
| critical | archives / listing | 2 | 12.8% | 0.0% | `/2022/11/couple-commits-2-million-to-fund-groundbreaking-clinical-trials/feed`, `/2022/11/dana-farber-marathon-challenge-raises-nearly-7-million-in-support-of-claudia-adams-barr-program-investigators/feed`; see `parity/reports/latest-report.html` | Views pagination, filters, infinite scroll vs WP grid plugin. |
| critical | homepage / hero | 1 | 54.2% | 5.4% | `/homepage`; see `parity/reports/latest-report.html` | Match front page block layout, hero region, featured story placement vs WP homepage template. |
| critical | global chrome (header, footer, nav, typography) | 3 | 38.1% | 38.5% | `/in-brief`, `/`, `/site/DocServer/Golf_2023_YearInReview.pdf`; see `parity/reports/latest-report.html` | Align DF Impact theme tokens, menu block placement, snippet HTML; single CSS pass on header/footer partials. |
| critical | navigation / IA | 1 | 41.8% | 0.0% | `/issues`; see `parity/reports/latest-report.html` | Path aliases, redirects, main menu structure from WP menus. |

## Content model & editor experience (static review)

- **Latest issue on live WP:** Spring 2026 content exists in committed `articles.json` / `in_brief.json`; fresh migrate import created `Spring 2026` issue node locally. Re-run `fetch_wordpress_model.py` on a machine with WP cookies before production cutover.
- **Media migration:** `df_media_images` failed in clean bootstrap (853 errors); inline images rely on committed `files/wp-images/` and remote URLs. Editors need reliable media library + focal point workflow.
- **End-to-end issue publish:** Issue is a node + articles reference issue placement fields — verify editorial checklist in `DRUPAL_REBUILD_PLAN.md` §editorial; no automated test yet.
- **Forms:** No public submission forms identified on WP templates (search + navigation only). Drupal contact/webform modules not required for parity.

## Owner actions

- Provide periodic **SQL dump** OR browser **cookies.txt** for SiteGround so CI/agents can refresh manifest and migration JSON.
- Confirm whether **SearchStax** keys are needed for local parity (search template optional).
