---
name: df-impact-wp-parity-loop
description: >-
  Runs an end-to-end WordPress→Drupal visual/functional parity loop for Dana-Farber
  Impact: compare a df-impact.ddev.site URL against its danafarberimpact.org twin,
  produce a gap analysis from screenshots and live browser inspection, plan and
  implement theme/markup fixes, QA site-wide regressions, then commit. Use when
  the user asks to run df-impact-wp-parity-loop, WP parity, WordPress parity,
  visual parity against danafarberimpact.org, or to close styling/nav/header/footer
  gaps between DDEV Drupal and WordPress.
disable-model-invocation: true
---

# DF Impact WP Parity Loop

Close visual and interaction gaps so local Drupal (`*.ddev.site`) matches production WordPress (`danafarberimpact.org`). WordPress is the source of truth.

## Inputs

Require both URLs before starting. Accept env-style args or prose:

| Input | Required | Default | Meaning |
|-------|----------|---------|---------|
| `DRUPAL_BASE_URL` | yes | — | Full DDEV page URL (e.g. `http://df-impact.ddev.site/category/cancer-research`) |
| `WP_BASE_URL` | yes | — | Matching WP page URL (e.g. `https://danafarberimpact.org/category/cancer-research/`) |
| `PARITY_SCOPE` | no | `full` | `full` \| `chrome` \| `content` \| `page` — see Scope |
| `SKIP_COMMIT` | no | `0` | `1` skips the final git commit |
| `VIEWPORTS` | no | `desktop,mobile` | Comma list: `desktop` (1440), `tablet` (768), `mobile` (390) |

If only one URL is given, derive the twin by swapping hosts and normalizing trailing slashes. Confirm the pair with the user when ambiguous.

## Scope

- **`page`**: Target page body only (listings, article, pagination, typography).
- **`chrome`**: Global header, nav (including homepage vs interior differences), footer, search/donate, translate, scroll behavior.
- **`content`**: Same as `page` plus in-page modules (cards, heroes, CTAs).
- **`full`**: `chrome` + `content` + smoke QA on related routes (home, one interior, search if touched).

Always inspect header/footer on **both** homepage and the target page. WP uses a reduced homepage nav; Drupal mirrors this in `site-header.html.twig` via `is_front` / `is_about_impact`. Treat chrome mismatches as first-class gaps, not out of scope under `full` or `chrome`.

## Progress checklist

Copy and update throughout the run:

```
Parity loop:
- [ ] 0. Preconditions (DDEV up, URLs resolve)
- [ ] 1. Capture baselines (WP + Drupal screenshots + snapshots)
- [ ] 2. Gap analysis document
- [ ] 3. Implementation plan
- [ ] 4. Implement fixes
- [ ] 5. Re-capture + close gaps (loop until accept or blocked)
- [ ] 6. Site-wide QA
- [ ] 7. Commit (unless SKIP_COMMIT=1)
- [ ] 8. Final report to user
```

## Phase 0 — Preconditions

1. Confirm DDEV responds: `curl -sI "$DRUPAL_BASE_URL" | head -5` (or open in browser).
2. Confirm WP responds.
3. Identify page type from path (home, category/topic archive, article, issue, search, static page, digital exclusives, etc.).
4. Read [reference.md](reference.md) for theme file map and gap/QA templates.

Do not start coding before Phase 2 exists as a written gap list.

## Phase 1 — Capture baselines

Use the **cursor-ide-browser** MCP (navigate → lock → snapshot/screenshot → unlock when done).

For **each** viewport in `VIEWPORTS`, capture WordPress then Drupal:

1. Full-page (or above-fold + scrolled key sections) screenshots.
2. Accessibility snapshots for structure (nav, headings, lists, pager).
3. Interactive states when relevant: nav hover/dropdown, mobile menu open, sticky header after scroll, pagination next/prev, search focus.
4. For `full` / `chrome`: also capture **homepage** header/footer on both sites at desktop + mobile.

Save artifacts under `.parity/<date>-<slug>/` (create if needed):

```
.parity/YYYY-MM-DD-<slug>/
  wp-desktop.png
  drupal-desktop.png
  wp-mobile.png
  drupal-mobile.png
  wp-home-header-desktop.png
  drupal-home-header-desktop.png
  gaps.md
  plan.md
```

Prefer reading screenshot images with the Read tool after capture so visual diffs are evidence-based.

## Phase 2 — Gap analysis

Write `.parity/.../gaps.md` using the template in [reference.md](reference.md).

Compare systematically (do not stop at the first obvious bug):

1. **Chrome**: logo size/position, nav items and order, homepage-only vs interior nav, dropdowns, search icon, Donate, GTranslate, mobile hamburger, sticky/scroll shrink, footer columns/links/social/legal.
2. **Layout**: containers, grids, gutters, section stacking, sidebar.
3. **Typography**: font family, size, weight, line-height, letter-spacing, link styles.
4. **Spacing**: margins/padding between modules; card gaps; section rhythm.
5. **Components**: cards, heroes, CTAs, breadcrumbs, category labels, dates, bylines.
6. **Motion**: hover, transitions, sticky header, menu open/close — match WP timing/easing when obvious.
7. **Pagination**: markup, placement, active/disabled states, ellipsis.
8. **Content parity** (flag only): missing/extra items vs WP may be content/migration, not theme — mark `type: content` vs `type: theme`.

Severity:

- **P0** — Unmissable chrome or layout break; wrong nav model; broken pager.
- **P1** — Clear styling/spacing/font mismatch on primary content.
- **P2** — Polish (1–2px, subtle hover, minor color).

Every gap needs: what differs, evidence (screenshot/DOM), likely Drupal file(s), severity.

## Phase 3 — Plan

Write `.parity/.../plan.md`: ordered fix list (P0→P2), files to touch, risks (global CSS bleed), and QA routes.

Prefer smallest change that matches WP:

1. Twig structure / classes in `df_impact` templates.
2. CSS in `css/layout.css`, `theme.css`, `components.css`, `base.css`.
3. JS in `js/main.js` (header scroll, menus) only when behavior diverges.
4. Config/views only when structure cannot be fixed in theme.

Avoid drive-by refactors and unrelated content edits.

## Phase 4 — Implement

Apply plan items. After each logical group (e.g. header, then listing cards):

1. Soft-reload Drupal page (hard refresh if CSS/JS).
2. Re-screenshot the affected viewport(s).
3. Mark gaps fixed / remaining in `gaps.md`.

If a gap needs WP-only assets or migration data, document as **blocked** with reason; continue other gaps.

## Phase 5 — Convergence loop

Repeat capture → compare → fix until:

- All P0/P1 theme gaps closed, or
- Remaining items are P2-only / blocked / content, and user scope does not demand pixel-perfect P2.

Stop and report if four consecutive fix attempts fail with no visual progress.

## Phase 6 — QA (required before commit)

Follow the QA checklist in [reference.md](reference.md). Minimum:

1. Re-compare target URLs at desktop + mobile (screenshots).
2. Homepage + one other interior page: header/footer/nav correct for each context.
3. Click-test: main nav targets, Donate, Search, mobile menu, footer links that were touched.
4. Pagination on the target listing if present.
5. No console-breaking theme JS errors on exercised pages.
6. Spot-check that unrelated templates still render (article full, home) when global CSS changed.

Fix regressions before committing.

## Phase 7 — Commit

If `SKIP_COMMIT=1`: leave changes unstaged/uncommitted; summarize diff for the user.

Otherwise commit only parity-related files (theme/CSS/JS/Twig/config touched for gaps). Follow the repo git commit user rules (status/diff/log → stage → HEREDOC message → status). Do not push unless asked.

Suggested message shape:

```
fix(theme): closer WP parity on <page-or-chrome>

Align Drupal <areas> with danafarberimpact.org for <path>.
```

Include `.parity/.../gaps.md` in the commit only if the user wants artifacts tracked; default **exclude** `.parity/` screenshots from git (add to commit only the code fixes). Prefer listing `.parity/` in a local ignore if it would otherwise be noisy; do not expand scope to rewrite root `.gitignore` unless needed.

## Phase 8 — Final report

Return a short report:

1. URL pair and scope.
2. P0/P1 fixed vs remaining/blocked.
3. Files changed.
4. QA result (pass/fail notes).
5. Commit hash or “skipped commit”.
6. Path to `gaps.md` / `plan.md`.

## Hard rules

- WordPress visual/IA behavior wins over Drupal “improvements.”
- Do not invent new design; match WP.
- Homepage nav may differ from interior — verify both; do not “unify” them unless WP does.
- Do not run destructive git commands; do not push; do not commit secrets.
- Do not mention skill internals in user-facing chatter beyond the report.
- Use browser tools for evidence; do not claim parity from code reading alone.

## Additional resources

- Theme map, gap template, QA checklist: [reference.md](reference.md)
- Invocation examples: [examples.md](examples.md)
