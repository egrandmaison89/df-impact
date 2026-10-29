# Drupal image audit (template sample paths)

Script: `parity/scripts/audit-drupal-images.mjs`  
Run: `DRUPAL_BASE_URL=http://127.0.0.1:8888 node parity/scripts/audit-drupal-images.mjs`

## Latest run (2026-10-29)

| Metric | Before fix | After attachment/stem resolver |
| --- | ---: | ---: |
| Images checked | 51 | 51 |
| Broken `src` (HEAD fail) | 13 | 9 |

Remaining failures are mostly missing local files under `wp-content/uploads/…` (not yet synced to `wp-images/`) or footer logos; live WP may still serve them via CDN.

Reports: `audit-2026-10-29T05-12-12-463Z.json` (before), `audit-2026-10-29T05-13-26-135Z.json` (after).
