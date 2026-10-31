# WordPress front-end port (reference-pack driven)

**Authority:** `parity/reference-pack/` (branch `reference-pack-2026-10-28`).

## Rules

1. Match **DOM + class names** from `rendered.html` per template.
2. Load **WP compiled CSS/JS** from `reference-pack/assets/` via theme library `wp_port` (self-hosted).
3. One **component per commit** with `parity/reports/progress/<component>/summary.json` + diff PNGs.
4. Gate: **≤5% viewport pixel median** at 1440/768/375 before next component.

## Component order

1. `chrome-global` — header, supplement, mega menu shell, footer, body classes
2. `homepage` — hero video cover, PostX grids
3. `issue` — issue landing bands
4. `article` / `in_brief` — entry content, sidebars
5. `archives` — listings, pagination

## Self-verify (VM / DDEV)

```bash
cd parity
export DRUPAL_BASE_URL=http://127.0.0.1:8888
export PROGRESS_COMPONENT=chrome-global
export PACK_COMPARE_PATHS=/homepage
node scripts/compare-reference-pack.mjs
```
