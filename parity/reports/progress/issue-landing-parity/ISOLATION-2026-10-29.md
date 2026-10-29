# Issue landing isolation (PR #22 vs content-sweep comparator)

**Branch under test:** `origin/cursor/issue-landing-parity-185b` (worktree `/tmp/issue-pure-185b`, commit `58056ac8`)  
**DB:** shared `df_impact`; `df_setup` schema reset to **10026** then **`drush updb`** ran **`df_setup_update_10027`** successfully (not replay-only).  
**WP issue HTML:** refreshed `parity/cache/wp-issue-pages/` via `fetch-wp-issue-pages.mjs` (15/16 OK; `late-fall-2022-issue` archive error).

## Finding 1 — content-sweep ~0.03 is baseline + metric, not “Drupal broke”

For `/fall-2025-issue`, content-sweep was comparing Drupal to **`issues.json` export stub** (`wpSource: export`, ~9 chars of body text). That yields **~0.02 Jaccard** vs Drupal.

| WP baseline | Cheerio main text (chars) | vs Drupal Jaccard |
| --- | ---: | ---: |
| export stub | 9 | **0.019** |
| issue-cache (live landing HTML) | 1506 | **0.963** |

See `node parity/scripts/compare-issue-sweep-extract.mjs /fall-2025-issue`.

PR #22’s **`issue-live-text-sweep.mjs`** uses **cached full landing HTML** + Playwright **`extractMainText`** — not the export stub.

## Finding 2 — PR #22 scorer on this VM does **not** reproduce STATUS ≥98.9%

Same DB + cache, Drupal served from **pure PR #22** (`127.0.0.1:8889`) or **content-sweep** (`127.0.0.1:8888`):

| Scorer | Min | Median | ≥98% pass |
| --- | ---: | ---: | --- |
| `issue-live-text-sweep.mjs` (PR #22) | 0.85 | 0.88 | **0 / 15** comparable |
| `run-content-sweep.mjs` (issue-cache + `textSimilarity` for issues) | 0.91 | ~0.93 | **0 / 16** |

Reports: `issue-live-sweep-1793269043373.json`, `sweep-1793268733975.json`.

**Conclusion:** The **0.02–0.04** merge sweep scores are **export-stub artifact**. With correct WP landing HTML, both scorers land in the **high 0.85–0.95** band on this DB — they **do not disagree** about order of magnitude; neither hits 98% until extract/normalization matches PR #22’s handoff run (likely different cache/fixture generation time).

## Finding 3 — content-sweep branch does not uniquely collapse issue-live scores

`issue-live-text-sweep` summaries were **identical** against `:8888` (content-sweep PHP) and `:8889` (pure PR #22 PHP). Issue landing gaps here are **data/cache parity**, not a content-sweep-only PHP regression.

## url:check regression

Failing path: **`/stories`** (Drupal 404). Fixed on PR #23 with `StoriesArchiveController` + route `/stories` → `stories_archive` view (200, same body as `/issues`).
