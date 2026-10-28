# Phase 2 — WXR export integration report

## WXR handling

- Owner export stored at `drupal/migration-data/private/wp-export.xml` (**gitignored**, contains staff emails).
- Parser: `drupal/migration-data/parse_wxr_export.py` → `media.json`, inventory, design tokens.
- See [WORDPRESS_WXR_EXPORT.md](./WORDPRESS_WXR_EXPORT.md).

## Media migration (`df_media_images`)

| Before | After (this work) |
| --- | --- |
| **853 failures** — `Destination property uri not provided` (broken `file_copy` mapping in migration YAML) | **`df_wp_upload_file`** process plugin writes `public://wp-content/uploads/{_wp_attached_file}` |
| Files only under flat `public://wp-images/` | **Same URLs as WordPress** via `web/wp-content/uploads` → `sites/default/files/wp-content/uploads` symlink |
| N/A | Sample URL **HTTP 200**: `/wp-content/uploads/2026/06/AICF_1450.jpg` |

**Import result (local, from WXR `media.json`):** **930 / 934** files imported; **4** failed (non-image or empty attachment metadata).

Re-import after pulling latest:

```bash
drush migrate:reset df_media_images -y
drush sqlq "TRUNCATE migrate_map_df_media_images"
drush migrate:import df_media_images
```

**Note:** Datacenter downloads may get SiteGround captcha (tiny HTML files). Mirror files under `migration-data/private/wp-uploads/` with the same relative paths as `_wp_attached_file`, then re-import.

## Content reconciliation (published posts + pages)

From `reconcile_wxr_drupal.py` (783 WXR publish rows):

| Metric | Count |
| --- | ---: |
| Path matched in Drupal | **664** |
| Missing in Drupal | **119** (110 posts, 9 pages) |

Missing **pages** are mostly archives (`/issues`, `/in-brief`, `/`) — Drupal uses Views routes; reconcile separately.

Missing **posts** are largely **2026-dated** stories present in WXR but not yet in committed `articles.json` (migration JSON refresh from WXR is follow-up).

## Global chrome (WXR-mined)

Reference: `drupal/migration-data/wp_design_tokens.json` (from `wp_global_styles`, `custom_css`, template parts, PostX).

| Token | WordPress (WXR) | Drupal `df_impact` |
| --- | --- | --- |
| Primary / links | `#00629b` | `--color-primary`, `--color-link` |
| Accent | `#ffa300` | `--color-accent` |
| Footer navy | `#003354` | `--color-footer-navy` |
| Header border | `#f4f4f4` | `--color-header-border` |
| Body / H1 | Montserrat 18px / Libre Baskerville 40px | Already aligned; tokens file documents WXR source |
| Content max width | `1490px` | `--container-max: 1490px` |

Full template HTML for header/footer/nav is in gitignored `wp_chrome_reference.json` for agent/owner reference when refining chrome.

## Parity smoke (post-rebase baseline)

Issue template desktop pixel median **~53%** (down from ~63% when issues returned 403). **Summer 2025 issue** desktop pixel **~42.5%** with owner theme commit on `main`. Use **frozen WP baseline** for trustworthy cross-site text/meta until captcha bypass.
