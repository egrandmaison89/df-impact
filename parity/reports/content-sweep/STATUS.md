# Content sweep STATUS

Latest artifact: `sweep-1793265982283.json` · CSV: `sweep-1793265982283.csv`

| Metric | Value |
| --- | --- |
| URLs checked | 801 |
| WP non-comparable (challenge / fetch fail) | 0 |
| Comparable (WP + Drupal 200) | 801 |
| **Pass** (≥98% text, 0 shortcodes, 0 wp-internal links, links OK) | **772** (96.4% of comparable) |
| ≥98% text similarity (comparable) | 772 |
| ≥98% text similarity (all 801 URLs) | 772 |
| Broken in-body links (total) | 0 |
| **In-scope pass** (excludes `issue` template — deferred to issue-landing PR) | 772 / 785 |

## Text similarity distribution (comparable)

| Bucket | Count |
| --- | --- |
| ≥ 0.98 | 772 |
| 0.95 – 0.98 | 0 |
| 0.90 – 0.95 | 0 |
| < 0.90 | 29 |

## Failure clusters (row may have multiple)

| Cluster | Rows |
| --- | --- |
| byline_date_mismatch | 759 |
| low_text_similarity | 29 |
| inline_image_count | 26 |
| pass | 11 |
| featured_image_mismatch | 10 |
| heading_title_mismatch | 7 |
| embed_count | 7 |

## Worst 50 (by text similarity, comparable only)

| Path | Template | Similarity | Shortcodes | WP int links | Clusters |
| --- | --- | --- | --- | --- | --- |
| /category/drug-development/page/2 | archive | 0.000 | 0 | 0 | low_text_similarity, featured_image_mismatch, heading_title_mismatch, inline_image_count |
| /category/drug-development/page/3 | archive | 0.000 | 0 | 0 | low_text_similarity, featured_image_mismatch, heading_title_mismatch, inline_image_count |
| /category/drug-development/page/4 | archive | 0.000 | 0 | 0 | low_text_similarity, featured_image_mismatch, heading_title_mismatch |
| /fall-2025-issue | issue | 0.039 | 0 | 0 | low_text_similarity, inline_image_count |
| /winter-2025-issue | issue | 0.039 | 0 | 0 | low_text_similarity, inline_image_count |
| /fall-2024-issue | issue | 0.040 | 0 | 0 | low_text_similarity, inline_image_count |
| /summer-2024-issue | issue | 0.040 | 0 | 0 | low_text_similarity, inline_image_count |
| /winter-2023-issue | issue | 0.040 | 0 | 0 | low_text_similarity, inline_image_count |
| /fall-2023-issue | issue | 0.041 | 0 | 0 | low_text_similarity, inline_image_count |
| /winter-2024-issue | issue | 0.041 | 0 | 0 | low_text_similarity, inline_image_count |
| /late-fall-2022-issue | issue | 0.041 | 0 | 0 | low_text_similarity, inline_image_count |
| /spring-2024-issue | issue | 0.043 | 0 | 0 | low_text_similarity, inline_image_count |
| /spring-2025-issue | issue | 0.043 | 0 | 0 | low_text_similarity, inline_image_count |
| /spring-2023-issue | issue | 0.043 | 0 | 0 | low_text_similarity, inline_image_count |
| /summer-2026-issue | issue | 0.043 | 0 | 0 | low_text_similarity, inline_image_count |
| /summer-2023-issue | issue | 0.056 | 0 | 0 | low_text_similarity, inline_image_count |
| /tag/late-fall-2022/page/2 | archive | 0.057 | 0 | 0 | low_text_similarity, featured_image_mismatch, heading_title_mismatch |
| /spring-2026-issue | issue | 0.156 | 0 | 0 | low_text_similarity, featured_image_mismatch, inline_image_count |
| /summer-2025-issue | issue | 0.173 | 0 | 0 | low_text_similarity, featured_image_mismatch, inline_image_count |
| /fall-2026-issue | issue | 0.213 | 0 | 0 | low_text_similarity, featured_image_mismatch, inline_image_count |
| /tag/late-fall-2022 | archive | 0.223 | 0 | 0 | low_text_similarity, heading_title_mismatch, inline_image_count |
| /category/immunotherapy | archive | 0.318 | 0 | 0 | low_text_similarity, inline_image_count |
| /category/pediatrics | archive | 0.322 | 0 | 0 | low_text_similarity, featured_image_mismatch, inline_image_count |
| /category/prevention-and-early-detection | archive | 0.383 | 0 | 0 | low_text_similarity, inline_image_count |
| /category/planned-giving | archive | 0.556 | 0 | 0 | low_text_similarity, inline_image_count |
| /category/grassroots-support | archive | 0.749 | 0 | 0 | low_text_similarity, inline_image_count |
| /category/drug-development | archive | 0.756 | 0 | 0 | low_text_similarity, inline_image_count |
| /category/access-and-equity | archive | 0.825 | 0 | 0 | low_text_similarity, inline_image_count |
| /category/discovery-science | archive | 0.871 | 0 | 0 | low_text_similarity |
| /about | landing_page | 0.993 | 0 | 0 | pass |
| / | homepage | 1.000 | 0 | 0 | pass |
| /2022/11/back-in-the-saddle-again-2022-pmc-raises-record-69-million-thats-900-million-since-1980 | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2022/11/back-on-course-with-the-boston-marathon-jimmy-fund-walk | article | 1.000 | 0 | 0 | heading_title_mismatch, byline_date_mismatch |
| /2022/11/couple-commits-2-million-to-fund-groundbreaking-clinical-trials | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2022/11/dana-farber-marathon-challenge-raises-nearly-7-million-in-support-of-claudia-adams-barr-program-investigators | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2022/11/dana-farber-team-defies-cancer-in-asics-falmouth-road-race | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2022/11/feenys-invest-in-new-treatments-for-advanced-prostate-cancer | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2022/11/gift-to-ovarian-cancer-inspired-by-story-of-research | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2022/11/ims-grants-drive-innovative-multiple-myeloma-research | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2022/11/johnston-gift-paves-the-way-for-a-novel-ovarian-cancer-clinical-trial | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2022/11/leadership-gift-establishes-poorvu-jaffe-chair-in-psychosocial-oncology-and-palliative-care-and-launches-health-equity-initiative | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2022/11/massachusetts-life-sciences-center-grants-2-8-million-to-establish-new-center-and-accelerate-drug-development | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2022/11/pbtf-accelerates-research-to-better-treat-pediatric-brain-tumors | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2022/11/rasmussen-family-funds-research-into-underlying-causes-of-colorectal-cancer | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2022/11/richard-and-susan-smith-family-receives-institutes-highest-honor-the-sidney-farber-medical-research-award | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2022/11/simons-foundation-grant-targets-cell-biology | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2023/01/alexs-lemonade-stand-foundation-grants-millions-to-advance-childhood-cancer-research | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2023/01/boston-red-sox-jimmy-fund-award | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2023/01/breast-cancer-alliance | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2023/01/burlingame-foundation | article | 1.000 | 0 | 0 | byline_date_mismatch |

## Re-run

```bash
cd parity
npm install
DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:content:sweep
node scripts/generate-content-sweep-status.mjs
```
