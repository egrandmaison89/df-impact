# WordPress ↔ Drupal parity harness

Compares **live WordPress** (`WP_BASE_URL`) to **local Drupal** (`DRUPAL_BASE_URL`) using the URL manifest under `manifest/`.

## Quick start

```bash
cd parity
npm ci
npx playwright install chromium
npm run manifest
# Terminal 2: drupal site on http://127.0.0.1:8888 (see docs/REPRODUCIBLE_ENVIRONMENT.md)
DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:sample
open reports/latest-report.html
```

## Commands

| Script | Purpose |
| --- | --- |
| `npm run manifest` | Live crawl + merge migration JSON |
| `npm run parity:sample` | Representative URLs × 3 breakpoints |
| `npm run parity:full` | Full manifest (slow) |
| `npm run report` | Regenerate HTML from latest run id |

## Outputs

- `reports/runs/<runId>/results.json` — machine-readable
- `reports/latest-report.html` — side-by-side + diff thumbnails
- `docs/PHASE1_GAP_REGISTER.md` — ranked gaps (after run)

## Environment variables

| Variable | Default |
| --- | --- |
| `WP_BASE_URL` | `https://danafarberimpact.org` |
| `DRUPAL_BASE_URL` | `http://127.0.0.1:8888` |
| `PARITY_SAMPLE` | `1` (set `0` for full manifest) |
| `PARITY_LIMIT` | optional cap |
| `PARITY_AXE` | set `1` to run axe on Drupal |
