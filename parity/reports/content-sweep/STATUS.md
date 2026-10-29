# Content sweep STATUS

Latest artifact: `sweep-1793262418687.json` · CSV: `sweep-1793262418687.csv`

| Metric | Value |
| --- | --- |
| URLs checked | 801 |
| WP non-comparable (challenge / fetch fail) | 31 |
| Comparable (WP + Drupal 200) | 770 |
| **Pass** (≥98% text, 0 shortcodes, 0 wp-internal links, links OK) | **757** (98.3% of comparable) |
| ≥98% text similarity (comparable) | 757 |

## Text similarity distribution (comparable)

| Bucket | Count |
| --- | --- |
| ≥ 0.98 | 0 |
| 0.95 – 0.98 | 2 |
| 0.90 – 0.95 | 2 |
| < 0.90 | 9 |

## Failure clusters (row may have multiple)

| Cluster | Rows |
| --- | --- |
| byline_date_mismatch | 759 |
| sweep_error | 20 |
| low_text_similarity | 13 |
| wp_non_comparable | 11 |
| embed_count | 7 |
| inline_image_count | 5 |
| heading_title_mismatch | 3 |
| featured_image_mismatch | 3 |
| pass | 2 |

## Worst 50 (by text similarity, comparable only)

| Path | Template | Similarity | Shortcodes | WP int links | Clusters |
| --- | --- | --- | --- | --- | --- |
| /stories | archive | 0.000 | 0 | 0 | low_text_similarity, heading_title_mismatch, inline_image_count |
| /search | search | 0.048 | 0 | 0 | low_text_similarity |
| /spring-2026-issue | issue | 0.129 | 0 | 0 | low_text_similarity, featured_image_mismatch, inline_image_count |
| /summer-2025-issue | issue | 0.138 | 0 | 0 | low_text_similarity, featured_image_mismatch, inline_image_count |
| /fall-2026-issue | issue | 0.172 | 0 | 0 | low_text_similarity, featured_image_mismatch, inline_image_count |
| /category/access-and-equity | archive | 0.731 | 0 | 0 | low_text_similarity |
| /category/digital-exclusives | archive | 0.852 | 0 | 0 | low_text_similarity, inline_image_count |
| /2025/06/breast-cancer-research-foundation-grants-more-than-4-million-to-speed-dana-farber-research | article | 0.884 | 0 | 0 | low_text_similarity, byline_date_mismatch |
| /2024/09/the-ambrose-monell-foundation-fuels-critical-immunotherapy-priorities | article | 0.896 | 0 | 0 | low_text_similarity, byline_date_mismatch |
| /2023/01/tim-and-ginny-bliss-endow-investigatorship-with-2-2-million-gift | article | 0.939 | 0 | 0 | low_text_similarity, byline_date_mismatch |
| /about | landing_page | 0.944 | 0 | 0 | low_text_similarity |
| /category/cancer-research | archive | 0.956 | 0 | 0 | low_text_similarity |
| /2025/03/khannas-generous-gift-helps-breast-cancer-patients-navigate-care | article | 0.957 | 0 | 0 | low_text_similarity, byline_date_mismatch |
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
| /2023/01/cancer-research-institute-invests-in-the-people-behind-basic-research-discoveries-in-cancer-immunology | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2023/01/cross-check-cancer-womens-hockey-tournament | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2023/01/dana-farber-cancer-institute-presidential-symposium | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2023/01/dana-farber-joins-with-parker-institute-for-cancer-immunotherapy-to-accelerate-cures-for-the-worlds-gravest-cancers | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2023/01/dana-farbers-b-a-a-half-team-runs-13-1-miles-to-defy-cancer | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2023/01/edward-p-evans-foundation-grants-propel-mds-research | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2023/01/emerald-foundation-inc | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2023/01/endowed-chair-honors-the-legacy-of-david-livingston-md | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2023/01/expect-miracles-foundation-raises-record-1-25-million | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2023/01/fellowship-program-in-hematology-oncology-chair-at-dana-farber-celebration | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2023/01/fred-and-michele-brettschneider | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2023/01/givingtuesday-raises-1-7-million | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2023/01/grants-support-innovations-in-pediatric-cancer-research | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2023/01/holiday-patient-assistance | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2023/01/jimmy-fund-golf-celebrates-40-years-of-defying-cancer-from-the-fairways | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2023/01/jog-for-julie | article | 1.000 | 0 | 0 | byline_date_mismatch |
| /2023/01/karen-oconnor-and-bruce-macgregor | article | 1.000 | 0 | 0 | byline_date_mismatch |

## Re-run

```bash
cd parity
npm install
DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:content:sweep
node scripts/generate-content-sweep-status.mjs
```
