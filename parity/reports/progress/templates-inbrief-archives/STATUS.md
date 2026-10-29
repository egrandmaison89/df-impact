# templates-inbrief-archives agent status

Branch: `cursor/templates-inbrief-archives-185b`  
Base: `cursor/phase4-chrome-behavior-0b67`  
PR: [#13](https://github.com/egrandmaison89/df-impact/pull/13)

## Phase4 merge + digital exclusives

- Merged `origin/cursor/phase4-chrome-behavior-0b67` (PR #5 base).
- Coordinated `df_setup_views_query_alter()` with VM fix: `digital_exclusives_archive` restricted to `is_digital_exclusive` migration aliases (`df_setup_digital_exclusive_archive_slugs()`).
- Archive pager **10** items/page (WP parity); first row matches ref (GivingTuesday / Capri image).

## URL behavior

| Path | Drupal | Notes |
| --- | ---: | --- |
| `/home` | **200** | `df_setup_update_10027` restores `/home` → Home node (same as `/homepage`) |
| `/category/pediatrics` | **200** | Pediatrics topic term (`10026`) |
| `/browse-issues` | **200** | Issue grid order matches WP ref (custom season sort); covers backfilled |

## Text parity (desktop, linux ref HTML, 2026-10-29)

| Path | Text |
| --- | ---: |
| `/in-brief` | **99.7%** |
| `/browse-issues` | **100%** |
| `/category/digital-exclusives` | **99.0%** |

Harness: `PARITY_COMPARE_LINUX=1 PACK_COMPARE_PATHS=… node parity/scripts/compare-reference-pack.mjs`  
Normalization: punctuation / camelCase spacing in `parity/lib/page-utils.mjs`; In Brief uses live `innerText` when grouped listing present.

## Sample text parity (ref-pack–backed paths only)

Mac/linux ref pack has **4 articles + 3 issues** with `rendered.html`. Sample run (`parity/scripts/sample-text-parity.mjs`):

- **Articles (4/4):** all ≥ **98%** (min **99.4%**).
- **Issues (0/3):** **~21–31%** — issue landing body vs ref still missing much issue chrome/article list text (follow-up, not in-brief/archive scope).

## wp-images (VM)

- Restore tooling: `parity/scripts/restore-bad-wp-images.mjs`, `restore_wp_images.php`.
- Magic-byte / `file` scan: run `find wp-images -type f -exec file -b {} \; | rg -c 'HTML|ASCII text|XML'` — target **0** HTML stubs (restore may still be running on long CDN failures).
- **Do not commit** binary churn under `sites/default/files/wp-images` unless verified valid and absent on origin.

## Pre-push gates

```bash
drush -r web cr
cd parity && DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:template-samples:http
cd parity && DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:smoke:200
```

Both passed on VM (2026-10-29).
