# WordPress WXR export (authoritative content source)

The owner provides a full **Tools → Export → All content** WXR file. It is **not committed** (contains staff author emails).

## Expected location

```
drupal/migration-data/private/wp-export.xml
```

Optional offline media mirror (same paths as `_wp_attached_file`):

```
drupal/migration-data/private/wp-uploads/2026/03/example.jpg
```

## Regenerate migration JSON from WXR

```bash
cd drupal/migration-data
python3 process_wxr_data.py      # full articles / in_brief / issues / taxonomies → df_migrate/data
python3 parse_wxr_export.py      # media + chrome reference (optional if only refreshing attachments)
python3 reconcile_wxr_drupal.py  # after Drupal is up
```

Outputs (gitignored private dir except `.gitkeep`; `media.json` is committed when refreshed):

| File | Purpose |
| --- | --- |
| `media.json` | Attachments for `df_media_images` |
| `wxr_content_inventory.json` | Published posts/pages + permalinks |
| `wp_chrome_reference.json` | Templates, nav, PostX, global styles |
| `wp_design_tokens.json` | Extracted colors/fonts/spacing hints |
| `wxr_drupal_reconcile.json` | Missing/extra paths vs Drupal |

## Drupal media import

After refreshing `media.json`:

```bash
cd drupal
export DDEV_DB_PORT=<host port>
./vendor/bin/drush -r web migrate:reset df_media_images -y
./vendor/bin/drush -r web migrate:import df_media_images
```

Files land under `public://wp-content/uploads/…` and are web-visible at `/wp-content/uploads/…` via symlink in `web/wp-content/uploads`.
