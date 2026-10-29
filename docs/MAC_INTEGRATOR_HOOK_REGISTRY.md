# Mac integrator — `df_setup` hook registry (binding)

Authoritative rules for merges onto `cursor/phase4-chrome-behavior-0b67` (PR #5).

## Frozen numbered hooks (10027–10035 only)

**Policy (effective now):** **No new `df_setup_update_*` numbers.** Schema cap **`10035`**. All further data/backfill work uses **name-based** functions in **`df_setup.post_update.php`** (Drupal tracks by name, not order).

**Reject any branch that:**

- Adds **`df_setup_update_10036`** or higher, or
- **Redefines 10027–10035** differently from phase4.

| Hook | Owner on phase4 | Purpose |
|------|-----------------|--------|
| **10027** | **#22 / #27** | Issue fixture backfill |
| **10028** | **#23** (partial) | `/about` staff sync |
| **10029** | **#25** (partial) | Gutenberg About body |
| **10030** | **#25** (partial) | Staff sync on Gutenberg body |
| **10031–10033** | — | **Never numbered on phase4** → **#23 post_updates** |
| **10034** | **#24** | Search parity (`df_secondary`, `search-legacy`) |
| **10035** | **#24** | Home landing / `page.front` |

**Upcoming post_updates (by name, on merge branches):**

| PR | Was / topic | Delivery |
|----|-------------|----------|
| **#23** | 10031–10033 | `df_setup_post_update_*` content-sweep backfills |
| **#25** | cover seed | `df_setup_post_update_*` media/cover seed |
| **#10** | `is_light` | **config import** + `df_setup_post_update_*` backfill (**stacked on #25**) |

## Merge order (whole merges only)

1. **#23** whole — **merged** (`ceff3720` ← `193ffdcf`)
2. **#25** whole — **merged** (`5b423ed8` ← `4884c8ab`); pass-3 live-WP CS 60/60
3. **#29** whole — content-sweep follow-up (`cursor/sweep-801-followup-ce7f`); fixes **794/800** (and integrator **793/800**) sweep gaps — **not ready**; merge only when branch agent reports ready
4. **#28** whole — article variants (**#24 already merged**; do not double-merge) — when agent ready
5. **#10** whole — **stacked on #25** (`is_light` + post_update) — after **#28**
6. **Eric QA (links / images / translate icon)** — targets phase4; whole merge when branch agent reports ready
7. **Eric QA (share / pagination / In Brief / tablet spacing)** — targets phase4; whole merge when branch agent reports ready

**After #25:** **#29 → #28 → #10 → Eric QA #1 → Eric QA #2** (each **whole merge once**; wait for branch agent “ready”; same post-merge gates below).

**Done on phase4:** **#22/#27** (`6622635f`, `fcf27bd2`), **#24** (`7c27d051`), **#23** (`ceff3720`), **#25** (`5b423ed8`).

**Post_updates (combined on phase4):** #23 four + #25 `seed_issue_covers`, `seed_issue_banners` (+ #10 `is_light` when merged).

On `df_setup.module` conflicts with #22, **keep #22’s side**.

## PR #25 (merged)

Whole merge at **`5b423ed8`** from `cursor/gutenberg-hover-archive-2b1b` @ **`4884c8ab`**. Hold cleared: pass-3 live-WP CS **60/60**, diff audit (reports-only deletions).

Computed-style acceptance on branch: `npm run parity:computed-style-diff:full:live-wp` (not refpack/pass-3 totals).

**Post-merge:** if `cim` resets `system.site:page.front`, run `_df_setup_ensure_home_landing()` (or re-verify anonymous `/` is **200**).

## PR #29 (not ready)

Branch **`cursor/sweep-801-followup-ce7f`** — rebasing onto **`5b423ed8`** (#25 merge) to fix **`/category/immunotherapy`** and remaining content-sweep gaps (integrator **793/800** text98; `/about`, gift/grant articles, etc.).

**Do not merge** until the branch agent reports ready.

**Integrator requirement before merge:** **revert any `/topics` “fix”** on that branch. Live WordPress returns **404** for `/topics`; **#24’s Drupal 404** for `/topics` is correct. A topics index route or 200 response must not ship in #29.

## Post-merge checklist (DB at schema 10035)

Run after **every** whole merge onto phase4 (#29, #28, #10, Eric QA PRs, etc.).

```bash
cd drupal
./vendor/bin/drush cim -y              # when branch ships config
./vendor/bin/drush updb -y
./vendor/bin/drush updatedb:status    # must show no pending updates
./vendor/bin/drush php:script web/modules/custom/df_setup/scripts/verify_df_setup_schema.php
./vendor/bin/drush php:script web/modules/custom/df_setup/scripts/verify_df_setup_post_updates.php
# If anonymous GET / is 404 after cim: drush php:eval "\Drupal::moduleHandler()->loadInclude('df_setup', 'install'); _df_setup_ensure_home_landing();" && drush cr

cd ../parity
npm run parity:smoke:200                              # 0 fails
npm run parity:template-samples:http                    # 19/19
npm run parity:url:check                              # 801/801
PARITY_CONTENT_SWEEP_NO_LIVE_WP=1 npm run parity:content:sweep   # ≥799 text98 among comparable
npm run parity:issue-live-text:cache                    # 15/15 (0 below98)
npm run parity:content:sweep:issues:cache               # 15/15 text98
npx playwright test --project=editor-e2e                # green
cd ../drupal && ./vendor/bin/drush -r web df-editor:test-issue-e2e   # 10/10
```

**`/browse-issues` gate:** live WP and Drupal must both expose **15** `figure.issue-cover` elements (XPath: `//figure[contains(concat(' ', normalize-space(@class), ' '), ' issue-cover ')]`). Quick check:

```bash
curl -s http://127.0.0.1:8888/browse-issues | php -r '$d=new DOMDocument(); @$d->loadHTML(stream_get_contents(STDIN)); $n=(new DOMXPath($d))->query("//figure[contains(concat(\" \", normalize-space(@class), \" \"), \" issue-cover \")]"); echo $n->length, "\n";'
# expect: 15
```

**Issue gates:** committed `parity/cache/wp-issue-pages` + refpack only (`PARITY_ISSUE_WP_CACHE_ONLY=1`).

### PR #23 (merged)

Whole merge onto phase4 at **`ceff3720`**. Pre-merge gate was `parity:url:check` **801/801** on `cursor/content-sweep-ce7f`.

## Phase4 reference state

| Check | Expected |
|-------|----------|
| `df_setup` schema | **10035** |
| Numbered hooks in code | **10027–10030**, **10034–10035** only |
| `df_setup.post_update.php` | name-based only beyond cap |
| Cache-only issue gates | **15/15** both scorers |
