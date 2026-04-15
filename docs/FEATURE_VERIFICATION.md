# Feature verification (failure-first)

After adding or changing user-facing behavior, **confirm the change with an automated check** before considering the task done. Guessing from code review alone is not enough.

## What to do

1. **Define a failure test** for the feature: a function or script that returns **specific failure messages** (or exits non-zero) when the product regresses. Prefer checks that exercise the real Drupal stack (rendered HTML, routes, config), not only unit logic.
2. **Run that check** in the environment where the site runs (e.g. DDEV) and fix any failures.
3. **Commit the verifier** beside the feature (script, PHPUnit test, etc.) so the next change can re-run it.

This applies to navigation pages, listings, forms, migrations, and any ticket described as a “gap” or “parity” with another site.

## In Brief archive (`/in-brief`)

Grouped-by-issue listing is validated by:

```bash
cd drupal
./vendor/bin/drush --uri=http://df-impact.ddev.site php:script scripts/verify-in-brief-listing.php
```

- Exit code **0**: checks passed.  
- Exit code **1**: stderr lists concrete failures (e.g. missing issue headings, grouping empty while nodes exist).

Adjust `--uri` to match your local base URL.

## Adding verifiers for new features

- Put scripts under `drupal/scripts/` with a name like `verify-<feature>.php`.
- Document the command in this file (short subsection per feature).
- For complex features, add a PHPUnit test under a `tests/` tree and wire it in CI when available.

## For automation agents

When implementing a new feature:

1. Add or extend a **failure test** (script or test class) that would have caught the bug you fixed.
2. Run it and ensure exit code 0.
3. Mention the verifier in the PR or commit message.
