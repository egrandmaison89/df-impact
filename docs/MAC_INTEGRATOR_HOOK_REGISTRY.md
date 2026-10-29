# Mac integrator — `df_setup` hook registry (binding)

This document is the **authoritative numbering** for merging feature branches onto `cursor/phase4-chrome-behavior-0b67` (PR #5). **Refuse to merge any branch whose `df_setup_update_*` numbers collide** with an already-assigned hook or a lower hook on the same branch.

## Assigned hooks (10027+)

| Hook | PR / source | Purpose |
|------|-------------|---------|
| **10027** | **#22** issue-landing | Issue fixture backfill: PostX sort + `backfill_issue_page_from_wordpress.php` (fixture-first). **Must run before content-sweep / Gutenberg About hooks.** |
| **10028** | **#23** content-sweep | `/about` staff block sync (WordPress reference). Renumbered from conflicting **10027** on #23. |
| **10029** | **#25** gutenberg-hover-archive | `/about` WordPress Gutenberg body. Renumbered from **10027** on #25. |
| **10030** | **#25** gutenberg-hover-archive | Idempotent staff sync on Gutenberg body. Renumbered from **10028** on #25. |
| **10031+** | **#24** article-variants-parity | Search parity (`df_secondary`, `search-legacy` view path) → **10031**. Home landing / `page.front` → **10032**. Any additional #24 hooks continue at **10033+**. |

Hooks **10025** (PostX fields), **10026** (Pediatrics term), and earlier remain unchanged.

## Merge order (queue)

1. **#22** issue-landing — establishes **10027** and `df_setup.module` issue helpers.
2. **#23** content-sweep — only after #22; uses **10028** (not 10027).
3. **#25** Gutenberg / archive / hover — after #22 and #23; uses **10029–10030** (not 10027–10028).
4. **#24** — after the above; hooks **10031+** only.

Do **not** merge #23 or #25 until their branches are renumbered on GitHub to match this table.

## Root cause: scratch merge (content-sweep + #22)

If **content-sweep’s hook ran as 10027 before #22’s 10027**, Drupal marks **10027** complete and **#22’s backfill never runs**. Symptom: **~16 issue landings drop to ~0.04 text parity** (empty/minimal body). A **`df_setup.module` merge conflict** that dropped #22-side code makes replay fail even after fixing hooks.

**Prevention:**

- Merge **#22 before #23 and #25**.
- On `df_setup.module` conflicts, **keep #22’s side** (issue landing / fixture replay helpers).
- After **every** merge: `drush updb -y`, then verify schema (below), then issue replay (below).

## Post-merge verification (required)

### Schema version

Highest executed hook must match the highest defined hook in `df_setup.install`:

```bash
cd drupal
./vendor/bin/drush php:eval '
  require_once DRUPAL_ROOT . "/modules/custom/df_setup/df_setup.install";
  $max = 0;
  foreach (get_defined_functions()["user"] as $fn) {
    if (preg_match("/^df_setup_update_(\\d+)$/", $fn, $m)) {
      $max = max($max, (int) $m[1]);
    }
  }
  $schema = (int) \Drupal::keyValue("system.schema")->get("df_setup");
  print "df_setup schema={$schema} max_hook={$max} " . ($schema === $max ? "OK\n" : "MISMATCH\n");
'
```

### Issue fixture replay (#22)

After **every** subsequent merge:

```bash
cd drupal
./vendor/bin/drush php:script web/modules/custom/df_migrate/scripts/test_issue_fixture_replay.php
```

Then score issue landing text (PR #22 gate):

```bash
cd parity
DRUPAL_BASE_URL=http://127.0.0.1:8888 node scripts/issue-live-text-sweep.mjs
```

**Pass:** replay exit 0; **15/15** issues with text parity **≥ 98.9%** vs WP reference (see sweep summary).

### Home after config import

If `drush cim` runs, re-point front page:

```bash
./vendor/bin/drush php:eval 'require_once DRUPAL_ROOT . "/modules/custom/df_setup/df_setup.install"; _df_setup_ensure_home_landing();'
```

## `/about` — #23 vs #25

Both touch About Impact. On integrator:

1. **10028** staff sync (content-sweep) runs before **10029–10030** Gutenberg.
2. Keep the branch/version that wins **combined text parity + computed-style vs WP**, without removing the **visible “About Impact” h1** (page title / template), even when body moves to Gutenberg blocks.

## Collision policy

Before merging branch `B`:

1. List `df_setup_update_*` on `B` and on the integrator branch.
2. If any number is reused for **different** behavior, **stop** — renumber on `B` first, then merge.

## PR #5 integrator branch (reference)

As of the registry commit, `df_setup.install` on `cursor/phase4-chrome-behavior-0b67` implements **10027–10032** exactly as in the table above.
