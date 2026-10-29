# Drupal image audit (801-path inventory)

Script: `parity/scripts/audit-drupal-images.mjs`  
Restore: `parity/scripts/restore-bad-wp-images.mjs`, `drush php:script …/restore_wp_images.php`  
Repair: `drush php:script …/repair_drupal_images.php`

## Site-wide run (2026-10-29)

| Metric | Before restore | After restore + style-aware audit |
| --- | ---: | ---: |
| Inventory paths | 801 | 801 |
| Unique `<img src>` checked | 1497 | 1498 |
| Broken (incl. page 404) | 33 | **2** |
| Broken on live WP too | 15 | **0** |
| Fixable image `src` (WP serves file) | 18 | **0** |

Remaining 2 failures are **page HTTP 404** only (`/home`, `/category/pediatrics`), not missing image binaries.

Reports:
- `audit-full-before.json` (33 broken)
- `audit-2026-10-29T06-11-36-186Z.json` (final)
- `latest-summary.json` (pointer to latest run)

```bash
DRUPAL_BASE_URL=http://127.0.0.1:8888 node parity/scripts/audit-drupal-images.mjs
AUDIT_SAMPLES=1 node parity/scripts/audit-drupal-images.mjs  # template samples only
```
