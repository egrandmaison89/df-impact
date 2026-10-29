# Nav link `background-color` — 3-run WP evidence (2026-10-29)

**Script:** `node parity/scripts/sample-wp-nav-bg.mjs 3`  
**Raw:** `2026-10-29T10-09-35-988Z.json`  
**Stability:** `unstableAcrossRuns: []` (every path×breakpoint identical across 3 runs)

## Tablet (768px) — per path (stable)

| Path | WP `background-color` |
| --- | --- |
| `/browse-issues` | `rgb(255, 255, 255)` |
| `/fall-2026-issue`, `/spring-2026-issue` | `rgb(255, 255, 255)` |
| `/category/*`, `/issues`, `/in-brief`, articles, `/homepage` (desktop nav N/A on mobile sample paths), etc. | `rgba(0, 0, 0, 0)` |

Distinct values at 768: **`rgb(255, 255, 255)`** and **`rgba(0, 0, 0, 0)`** — not a single global value, but **deterministic per URL**.

## Mobile (375px)

Distinct: **`rgba(0, 0, 0, 0)`** and **`rgb(255, 255, 255)`** (e.g. issue pages on mobile header).

## Gate decision

- **Removed** tablet/mobile `navDefault` field omission — parity requires matching WP per path.
- **Drupal fix:** show desktop chrome through **768px** (`max-width: 767px` mobile swap, not 768) + PR #25 white mega-link bg `@media (min-width: 768px)` so issue/landing paths match WP white where sampled.
