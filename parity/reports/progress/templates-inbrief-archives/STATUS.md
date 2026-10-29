# templates-inbrief-archives agent status

Branch: `cursor/templates-inbrief-archives-185b`  
Base: `cursor/phase4-chrome-behavior-0b67`

## Implemented (this PR)

- `/in-brief` listing: PostX `ultp-block-*` sections per issue, `Load More Briefs`, full + card view modes
- `/browse-issues`, `/past-issues`: `figure.issue-cover` + `h4.wp-block-heading` flex columns
- `/category/*`, `/digital-exclusives`: `wp-block-query` / `wp-block-post-template` rows
- Basic/`landing_page` nodes: `wp-block-heading` + `wp-block-post-content` wrappers (incl. About)
- `template-samples.json`: expanded in_brief, landing, archive paths

## Parity scores (`PARITY_COMPARE_LINUX=1`, `PROGRESS_COMPONENT=templates-inbrief-archives`)

### `/in-brief`

| BP | Structural (pack) | Structural (ref↔Drupal) | Linux viewport | Mac viewport |
| --- | --- | --- | --- | --- |
| 1440 | fail (4) — mostly header/footer chrome width | fail (52) — chrome + short page | **24.7%** | 22.2% |
| 768 | fail (5) | fail (18) | **12.6%** | 13.2% |
| 375 | fail (6) | fail (22) | **20.8%** | 19.9% |

### `/browse-issues`

| BP | Structural (pack) | Linux viewport | Mac viewport |
| --- | --- | --- | --- |
| 1440 | fail (4) — chrome | **24.6%** | **28.3%** |
| 768 | fail (5) | 12.4% | 26.5% |
| 375 | fail (6) | 19.4% | 28.7% |

### `/category/cancer-research` (partial run — desktop/tablet/mobile captured before about-impact timeout)

Linux viewport diffs captured under `category_cancer_research_*` (see `summary.json` when merged into full run).

## Blockers / evidence

1. **Content volume:** `df_in_brief` migration still running (~35/323 nodes at last check). Footer `y` and page height fail structural gates until full import + `df-migrate:sync-wordpress-paths`.
2. **Chrome (other agent):** header width `1296` vs `1440`, `.desktop-header` margin — dominates structural failure counts; not edited in this PR.
3. **Articles migration:** incomplete (`article` count low) — category archives and text-order gates need `df_articles` + `df_media_images` completion.
4. **about-impact compare:** Linux ref server `networkidle` timeout on pack HTML (investigate inline assets / third-party scripts in pack page).

## Next steps

1. Finish `df_in_brief`, `df_articles`, `df_media_images` migrations; re-run path sync + parity on full `template-samples.json` archive + in_brief paths
2. Re-run `/about-impact`, `/past-issues`, `/issues` compares
3. Text-similarity pass once article/in-brief counts match WP manifest
