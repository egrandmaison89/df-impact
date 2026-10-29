# Mac integrator — `df_setup` hook registry (binding)

Authoritative numbering for merges onto `cursor/phase4-chrome-behavior-0b67` (PR #5). **Refuse any branch whose `df_setup_update_*` numbers collide** with this table.

## Assigned hooks (10027+)

| Hook | PR | Purpose |
|------|-----|---------|
| **10027** | **#22** | Issue fixture backfill (`backfill_postx_sort_weights.php`, `backfill_issue_page_from_wordpress.php`). |
| **10028** | **#23** | Content-sweep: `/about` staff sync (renumbered off 10027). |
| **10029** | **#25** | `/about` Gutenberg body (renumbered off 10027). |
| **10030** | **#25** | Staff sync on Gutenberg body (renumbered off 10028). |
| **10031** | **#23** | Content-sweep (reserved on #23 branch). |
| **10032** | **#23** | Content-sweep (reserved on #23 branch). |
| **10033** | **#23** | Content-sweep (reserved on #23 branch). |
| **10034+** | **#24** | Search parity → **10034**; home landing → **10035**; further #24 hooks → **10036+**. |

Hooks **10025–10026** unchanged (PostX fields, Pediatrics term).

## Merge order (hard)

Drupal **never runs a hook numbered below the current schema**. If **#23** merges before **#25**, schema can reach **10033** while **10029/10030** were never registered → Gutenberg About hooks **never run**.

**Required order:**

1. **#22** issue-landing (**10027**)
2. **#25** Gutenberg / archive / hover — see **hold criteria** below (do **not** full-merge the +483k / 762-file PR until green)
3. **#23** content-sweep — **after #25**, and only **#23’s later commits** (hooks **10031–10033**). **#25 already merges `cursor/content-sweep-ce7f` (PR #23)** and carries **10028** plus **10029/10030**; do **not** re-apply the overlapping content-sweep body when landing standalone #23.
4. **#24** article variants (**10034+**)

If **#25 is not ready**, **hold #23** as well (schema / hook ordering).

On `df_setup.module` conflicts with #22, **keep #22’s side**.

## PR #25 hold (integrator does not merge yet)

GitHub **#25** (`cursor/gutenberg-hover-archive-2b1b`) **merges `cursor/content-sweep-ce7f` inside it**, stacks **10029/10030** on **#23’s 10028**, and is **~+483k lines / 762 files**. **Do not merge #25 onto PR #5** until its agent reports:

| Gate | Requirement |
|------|----------------|
| **(a) Computed-style** | **Pass-2 vs pass-3** compared on the **same live-WP baseline** (apples-to-apples); regressions explained. |
| **(b) Diff size** | **Trimmed generated artifacts** (no huge accidental report/refpack dumps in the merge diff). |
| **(c) Head meta** | **`parity:head:refpack` failures explained** (or fixed) on the PR branch. |

Prefer **surgical/cherry-pick** onto PR #5 (as with prior #25 work) unless the trimmed PR is explicitly approved.

### Computed-style baseline (PR #5 gates)

Integrator **computed-style full** must stay on the **pass-2 live-WP baseline**, **not** pass-3 / new refpack aggregate totals:

```bash
cd parity
DRUPAL_BASE_URL=http://127.0.0.1:8888 PARITY_WP_SOURCE=live npm run parity:computed-style-diff:full
```

Do **not** switch integrator acceptance to `PARITY_WP_SOURCE=refpack` (default in `run-computed-style-full.mjs`) or to new refpack mismatch sums from #25 until explicitly rebaselined.

## Post-merge checklist (every merge)

```bash
cd drupal
./vendor/bin/drush updb -y
./vendor/bin/drush php:script web/modules/custom/df_setup/scripts/verify_df_setup_schema.php
./vendor/bin/drush php:script web/modules/custom/df_migrate/scripts/test_issue_fixture_replay.php

cd ../parity
DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:content:sweep:issues
DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:issue-live-text
npm run parity:url:check    # must be 801/801 before merging #23
npm run parity:smoke:200    # 0 fails
# When rebaselining computed-style (optional; use live WP for integrator — see PR #25 hold):
# PARITY_WP_SOURCE=live npm run parity:computed-style-diff:full
```

**Pass:** `verify_df_setup_schema` exit 0 (`schema === max_hook`, `update_10027_applied` when max ≥ 10027). Record **both** issue text metrics in merge notes (live-text vs content-sweep can diverge; bad **10027** collision → ~0.03 sweep vs ≥98.9% live-text).

### PR #23 merge blockers

- **`parity:url:check` = 801/801** on the content-sweep branch (#23 reported **800/801** — do not merge until green).
- **#25 merged first** (standalone #23 only for **10031–10033** after #25; overlap already in #25).

### Schema + `df_setup_update_10027`

`10027` must run via **`drush updb`**, not replay-only. Sets `update_10027_applied`.

Recovery if marker missing but schema ≥ 10027:

```bash
./vendor/bin/drush php:eval '\Drupal::keyValue("system.schema")->set("df_setup", 10026);'
./vendor/bin/drush updb -y
```

### Home after `cim`

```bash
./vendor/bin/drush php:eval 'require_once DRUPAL_ROOT . "/modules/custom/df_setup/df_setup.install"; _df_setup_ensure_home_landing();'
```

## `/about` (#23 vs #25)

- **10029–10030** (#25) must be **present in `df_setup.install` before schema passes 10031** (merge **#25 before #23**).
- Pick About body/CSS that wins **text parity + computed-style vs WP** without losing visible **About Impact** h1.

## PR #5 integrator reference

`cursor/phase4-chrome-behavior-0b67` implements **10027–10030** and **10034–10035** (#24 search/home). Slots **10031–10033** remain for **#23** at merge time.

### Latest VM snapshot

| Gate | Result |
|------|--------|
| `verify_df_setup_schema` | schema=max_hook, `update_10027_applied` set |
| `parity:url:check` | **801/801** |
| `parity:smoke:200` | **0 fails** |
| `parity:issue-live-text` | min **0.989** (14/16 comparable) |
| `parity:content:sweep:issues` | min **~0.910** on comparable rows (log both) |
