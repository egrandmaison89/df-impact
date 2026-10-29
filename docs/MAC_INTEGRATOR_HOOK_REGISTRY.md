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

1. **#23** whole — **merged** (`ceff3720` ← `193ffdcf`); post_updates only (no numbered hooks above 10035)
2. **#25** whole — **on hold** until agent clears (a–c); verify pass-2 live-WP computed-style baseline
3. **#28** whole — article variants (**#24 already merged**; do not double-merge)
4. **#10** whole — **stacked on #25** (`is_light` + post_update)

**Done on phase4:** **#22/#27** (`6622635f`, `fcf27bd2`), **#24** (`7c27d051`), **#23** (`ceff3720`).

**#23 post_updates (combined on phase4):** `ensure_home_landing_published`, `in_brief_excerpt`, `seed_listing_excerpts`, `restore_in_brief_summary`.

On `df_setup.module` conflicts with #22, **keep #22’s side**.

## PR #25 hold

Do not merge until agent reports: **(a)** pass-2 vs pass-3 CS on same live-WP baseline, **(b)** trimmed artifacts, **(c)** `head:refpack` explained. Then merge **whole** branch.

Computed-style acceptance: `npm run parity:computed-style-diff:full:live-wp` (not refpack/pass-3 totals).

## Post-merge checklist (DB at schema 10035)

```bash
cd drupal
./vendor/bin/drush updb -y
./vendor/bin/drush updatedb:status    # must show no pending updates
./vendor/bin/drush php:script web/modules/custom/df_setup/scripts/verify_df_setup_schema.php
./vendor/bin/drush php:script web/modules/custom/df_setup/scripts/verify_df_setup_post_updates.php
./vendor/bin/drush php:script web/modules/custom/df_migrate/scripts/test_issue_fixture_replay.php

cd ../parity
DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:issue-live-text:cache
DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:content:sweep:issues:cache
npm run parity:smoke:200              # 0 fails
npm run parity:template-samples:http  # 19/19
npm run parity:url:check              # 801/801
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
