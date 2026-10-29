# Meta / head / behavior / URL agent status

Branch: `cursor/meta-head-behavior-0751` | PR: https://github.com/egrandmaison89/df-impact/pull/10  
Base: `cursor/phase4-chrome-behavior-0b67`

## Resume here

1. `git pull origin cursor/meta-head-behavior-0751 && cd drupal/web && ../vendor/bin/drush cr`
2. Strict cross-site smoke: `cd parity && DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:behavior:smoke`
3. Full templates: `npm run parity:behavior:templates` then `npm run parity:behavior:report-diff` (exits 1 if any mismatch rows)
4. Head manifest: `DRUPAL_BASE_URL=... npm run parity:head:manifest` → **773/773** expected
5. Read `reports/behavior/<runId>/wp-drupal-behavior-summary.json` → `mismatchRowsByTemplate`, `totalMismatchRows`

## Strict cross-site behavior gate

- **Assertion:** `cross.diff.zero_mismatch_rows` — fails if `buildCrossSiteReport()` has any section mismatch (not loose inventory-only checks).
- **Report:** `compare/<slug>_<bp>/wp-drupal-behavior-diff.json` + roll-up `wp-drupal-behavior-summary.json` (includes per-template mismatch row counts).
- **WP load:** 3× retry on goto; `wpSnapshotReady()` requires hero (homepage) + `navDefault` before diffing.
- **Hover compare:** `compareHoverStyles()` — `hover.changed` must match; when either side changes, compare **color**, **backgroundColor**, and **transform** (~3/255 color tolerance). No “only compare when both changed” shortcut.

## Compare exclusions (intentional — not gate loosening)

| Area | Rule |
| --- | --- |
| `hero` | Compared only on `/homepage` |
| `bodyLinkHover` | Compared only on content paths: `/YYYY/MM/…`, `/in-brief`, paths containing `-issue` |
| `issuesDropdown` | Desktop breakpoint only |
| `mobileMenu` | Mobile breakpoint only |
| Widget inventory (`widgets.*`) | Counts scoped to `main` / `.wp-site-blocks`; nodes under consent roots excluded |

## Nondeterminism handling (network / DOM)

- **Route abort:** cookie/consent vendor scripts (`cookiebot`, `onetrust`, `osano`, `trustarc`, `quantcast`, `iubenda`) blocked in `prepareBehaviorPage()` so banner DOM does not affect widget counts.
- **WP live load:** intermittent timeouts or incomplete hero/nav → `cross.wp.snapshot` (no diff for that case); not counted as a behavior mismatch row.

## Gates

| Gate | VM status |
| --- | --- |
| `parity:head:manifest` | **773/773** (`manifest-1793243108644.json`) |
| `parity:head:refpack` (21) | **21/21** |
| `parity:url:check` | **801/801** (`URL_PARITY_CONCURRENCY=1`) |
| `parity:behavior:smoke` (strict cross-site) | Iterating — homepage desktop **0** mismatch rows when WP snapshot complete |
| `parity:behavior:templates` (strict) | In progress (~48 cases × live WP) |

## Theme / harness fixes (recent)

- Sticky header, mega flyout `:hover`, scroll `.scrolled` / `.header--scrolled`, hero `play()`.
- Footer copyright link: scroll-into-view before capture; selector `footer .copyright a, p.copyright a`.
- Hover capture reads styles from the hovered locator (not a separate `querySelector`).
- `run-behavior-parity.mjs` always writes summary JSON after cross-site run (even when tests fail).

## npm

```bash
npm run parity:behavior:smoke          # strict cross-site
npm run parity:behavior:templates
npm run parity:behavior:report-diff    # non-zero exit if mismatch rows
npm run parity:behavior:templates:drupal-only
```

Updated: 2026-10-29T03:35Z
