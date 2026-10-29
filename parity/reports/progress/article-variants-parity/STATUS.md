# Article variant parity (PR #24)

Branch: `cursor/article-variants-parity-185b` → `cursor/phase4-chrome-behavior-0b67`.

## Harness

- Fixtures: `parity/cache/wp-article-variants/` (12 HTML files, 16 sample paths — some variants share paths).
- Fetch: `npm run parity:article-variants:fetch-wp`
- Compare: `REPORT_TAG=before|final npm run parity:article-variants:compare`
- Reports: `variant-parity-*.json` in this directory.

## Gates (VM `DRUPAL_BASE_URL=http://127.0.0.1:8888`)

| Gate | Result |
| --- | --- |
| `parity:template-samples:http` | **19/19** (`check-1793271415984.json`) |
| `parity:article-variants:compare` | **16/16** (`variant-parity-final-1793271407166.json`) |
| `parity:search:compare` | 4/4 (see search JSON in this folder) |

Home 404 fix: `df_setup_update_10028` / `_df_setup_ensure_home_landing()` ensures a **Home** page and sets `system.site:page.front` to `/node/{nid}` (VM: `/node/1`, alias `/homepage`).

## Per-variant scores (2 samples × 8 layout variants)

Text threshold 98%. Component probes: hero lead figure, sidebar alignright, pull quote, gallery, video iframe, custom Stackable blocks. **Before** = pre-fix run (`variant-parity-after2-1793265574824.json`, 7/16 pass). **After** = `variant-parity-final-1793271407166.json`, 16/16 pass).

| Variant | Sample path | Text before | Text after | Component mismatches before | Component mismatches after | Fixes |
| --- | --- | ---: | ---: | --- | --- | --- |
| hero | `/2022/11/back-in-the-saddle-again-2022-pmc-raises-record-69-million-thats-900-million-since-1980` | 99.74% | 100% | lead_figure rect/style | — | `article__body`, lead figure CSS (`is-resized` max-width), fixture `setContent` base URL, probe fallbacks for offline WP images |
| hero | `/2022/11/back-on-course-with-the-boston-marathon-jimmy-fund-walk` | 97.35% | 100% | lead_figure rect/style | — | Article byline **Photography by** line; same hero CSS/probes |
| no_hero | `/2023/01/boston-red-sox-jimmy-fund-award` | 94.64% | 100% | — | — | In Brief full **body** + manifest photo credit in byline |
| no_hero | `/2023/01/breast-cancer-alliance` | 100% | 100% | — | — | — |
| sidebar | `/2022/11/back-in-the-saddle-again-2022-pmc-raises-record-69-million-thats-900-million-since-1980` | 99.74% | 100% | alignright rect/style | — | Alignright `is-resized` sizing (same as hero) |
| sidebar | `/2022/11/back-on-course-with-the-boston-marathon-jimmy-fund-walk` | 97.35% | 100% | — | — | Photo credit in article byline |
| pull_quote | `/2022/11/back-in-the-saddle-again-2022-pmc-raises-record-69-million-thats-900-million-since-1980` | 99.74% | 100% | — | — | Pull quote block CSS in `components.css` |
| pull_quote | `/2022/11/couple-commits-2-million-to-fund-groundbreaking-clinical-trials` | 100% | 100% | — | — | — |
| gallery | `/2022/11/back-on-course-with-the-boston-marathon-jimmy-fund-walk` | 97.35% | 100% | — | — | Text parity via credits + body; gallery selector unchanged |
| gallery | `/2022/11/dana-farber-marathon-challenge-raises-nearly-7-million-in-support-of-claudia-adams-barr-program-investigators` | 97.78% | 100% | — | — | Photo credit / body parity |
| video_embed | `/2023/01/dana-farber-joins-with-parker-institute-for-cancer-immunotherapy-to-accelerate-cures-for-the-worlds-gravest-cancers` | 100% | 100% | — | — | — |
| video_embed | `/2023/09/advancing-equity-tops-agenda-at-womens-leadership-breakfast` | 97.17% | 100% | — | — | Photo credit in byline |
| long_form | `/2022/11/richard-and-susan-smith-family-receives-institutes-highest-honor-the-sidney-farber-medical-research-award` | 99.31% | 100% | — | — | — |
| long_form | `/2024/09/campaign-leadership-spurs-innovation-and-progress-at-dana-farber` | 99.35% | 100% | — | — | — |
| custom_blocks | `/2024/03/jimmy-fund-golf-community-helps-to-usher-in-the-jimmy-funds-75th` | 98.63% | 100% | — | — | Stackable CSS library; exclude Jetpack related posts from text extract |
| custom_blocks | `/2025/03/another-ace-year-for-jimmy-fund-golf` | 97.65% | 100% | — | — | Related-post chrome stripped from `extractMainText` |

## Code touched (summary)

- **Templates:** `node--article--full.html.twig` (photo credit, `article__body`), `node--in-brief--full.html.twig` (body + credits).
- **Theme:** `df_impact.theme` — in-brief byline/photo credit from migrate manifest.
- **CSS:** `components.css` — Gutenberg pull quote, gallery, floats, `is-resized` lead/sidebar widths.
- **Setup:** `df_setup_update_10028` — Home landing for template-samples `/` and `/homepage`.
- **Parity:** `fetch-wp-article-variant-fixtures.mjs`, `compare-article-variant-parity.mjs`, `article-variant-components.json`, `content-region.mjs` (drop related-post text from compare).
