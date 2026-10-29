# Issue landing Cheerio vs Playwright proof (2026-10-29)

## Question

Did 15/15 @ 100% come only from swapping the content-sweep scorer to Playwright while Cheerio stayed ~0.91–0.95?

**No.** Before the Drupal pagination fix, Cheerio lagged because **reader-visible PostX pagination** existed on WordPress but not in Drupal’s extracted main text. Playwright scored 1.0 for both sides because `parity/lib/content-region.mjs` strips `.ultp-pagination` / `.pager` from **both** baselines.

The only other Drupal content fix in `fcf27bd2` was **issue card title entity decode** (`Marathon®` vs `&lt;sup&gt;®&lt;/sup&gt;`).

## Cheerio-only diff (three samples, pre-pagination fix)

| Path | Cheerio Jaccard (WP cache vs Drupal) | Playwright sim | Tokens only on WP side | Reader-visible? |
|------|--------------------------------------|----------------|-------------------------|-----------------|
| `/fall-2025-issue` | ~0.941 | 1.0 | `previous`, `next`, page numbers `1–7` in **ultp** block | **Yes** on WP (centered PostX pager under grid). Drupal had Views `.pager` **outside** the PostX block (stripped by Cheerio `.pager` rule); no `.ultp-pagination`. |
| `/spring-2024-issue` | ~0.929 | 1.0 | Same pagination pattern | **Yes** on WP; **No** matching ultp pager on Drupal |
| `/summer-2023-issue` | ~0.920 | 1.0 | Same (fewer pages on smaller issues) | **Yes** on WP; **No** on Drupal |

### Snippet contrast (fall-2025, page 1)

**WordPress (Cheerio kept ultp pagination text):**

> …in this issue … **Previous 1 2 3 … 7 Next** … in brief …

**Drupal before fix (Cheerio dropped `.pager`, no ultp):**

> …in this issue … *(no pagination tokens)* … in brief …

**Drupal after fix (`pager--issue-postx` inside grid):**

> …in this issue … **Previous 1 2 3 … 7 Next** … in brief …

(Pagination matches WP; `Previous` is `display:none` on page 1 on both sites but still present in DOM like WordPress.)

## Post-fix scores (same Cheerio extractor, no scorer change)

| Path | Cheerio Jaccard |
|------|-----------------|
| `/fall-2025-issue` | **1.0000** |
| `/spring-2024-issue` | **1.0000** |
| `/summer-2023-issue` | **1.0000** |

Full gates (after pagination Drupal fix):

- Playwright `issue-live-text-sweep`: 15/15 compared, min text similarity **1.0** (`issue-live-sweep-1793274513282.json`)
- Cheerio `run-content-sweep` (issue paths only): 15/15 comparable, **passRateAmongComparable 1.0** (`sweep-1793274520171.json`)

## Desktop full-page screenshots

`/opt/cursor/artifacts/screenshots/issue-cheerio-proof/`:

- `fall-2025-issue-{drupal,wp}-full.png`
- `spring-2024-issue-{drupal,wp}-full.png`
- `summer-2023-issue-{drupal,wp}-full.png`

## Drupal change (not scorer)

- `views-view--issue-articles--block-1.html.twig` — PostX wrapper + pager inside block
- `views-view-unformatted--issue-articles--block-1.html.twig` — rows only
- `pager--issue-postx.html.twig` + `df_impact.theme` pager suggestions on issue node routes
