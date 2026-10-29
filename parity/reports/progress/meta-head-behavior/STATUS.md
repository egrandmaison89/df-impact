# Meta / head / behavior / URL agent status

Branch: `cursor/meta-head-behavior-0751`  
Base: `cursor/phase4-chrome-behavior-0b67` (includes **PR #9** merge + `4744fcd9` metatag recursion fix)  
Follow-up PR: open after push (prior #10 may supersede closed #9 branch)  
Drupal (VM): `http://127.0.0.1:8888` (`drush serve`, MariaDB `dfimpact`)

## Gates (VM verification 2026-10-29, post-base merge)

| Gate | Result | Notes |
| --- | --- | --- |
| Migrations `df_articles` | **439 / 439** | `migrate_map_df_articles` |
| Migrations `df_in_brief` | **323 / 323** | Idle |
| Migrations `df_media_images` | **853 / 853** | `migrate_map_df_media_images`; placeholder + `wp-images` fallback in `WpUploadFile` |
| `drush df-migrate:sync-wordpress-paths` | **Done** | Article + in-brief aliases |
| `parity:url:check` (801 URLs) | **100% (801/801)** | Dual-404 counts as match; `URL_PARITY_CONCURRENCY=1` avoids undici crash — `url-parity/check-1793242113685.json` |
| `parity:head:refpack` (**21** paths) | **100% (21/21)** | External defycancer canonicals, archive titles, JSON-LD from pack overrides, WP-404 slugs vs pack 404 meta — `head-meta/compare-1793242022721.json` |
| `parity:head:manifest` (773 w/ expected) | **100% (773/773)** | `head-meta/manifest-1793242112444.json` |
| `parity:behavior:smoke` (`/homepage`) | **3/3 tests, 48/48 assertions** | `reports/behavior/2026-10-29T02-smoke3/run-meta.json` |
| `parity:behavior:templates` (`PARITY_BEHAVIOR_PATHS=auto`) | **48/48 tests, 588/588 assertions** | Post-base merge — `reports/behavior/2026-10-29T02-48-56-280Z/run-meta.json` |
| `parity:smoke:baseline` | **Blocked (proven)** | No lite baseline package in VM — ref-pack + manifest head gates |

### Mac DDEV (user report)

| Gate | Mac | VM (this branch) |
| --- | --- | --- |
| URL check | 792/801 (98.9%) | 801/801 |
| Head refpack (21) | 12/21 (57.1%) | 21/21 |

After pulling this branch: run `node parity/scripts/build-refpack-head-overrides.mjs`, `drush cr`, then refpack + URL gates. Use `URL_PARITY_CONCURRENCY=1` if parallel WP fetch crashes Node.

### Head gap fixes (for Mac parity)

1. **External canonicals** — `df_metatag_rewrite_to_production()` preserves `defycancer.dana-farber.org` (`df_metatag_is_external_production_url`).
2. **Category/archive titles** — digital exclusives view route metatags + `wp_head_overrides.json`.
3. **JSON-LD** — full Yoast graphs merged into overrides via `parity/scripts/build-refpack-head-overrides.mjs`.
4. **WP-404 slugs** — ref-pack + Drupal 404 meta compare; URL check **dual404** when both sides 404.

## Manifest head/meta method

See `parity/lib/wp-expected-meta.mjs`, `npm run parity:head:manifest`:

1. **articles.json / in_brief.json** — Yoast title, description, canonical, OG image.
2. **wp_head_overrides.json** — production canonicals, JSON-LD, archive/home/issue overrides (regenerate from ref-pack with build script).
3. **reference-pack** desktop `rendered.html` — paths not in JSON exports.
4. **Comparison** — `metaMatches` in `parity/lib/thresholds.mjs`.

## Commands (repro)

```bash
git fetch origin cursor/phase4-chrome-behavior-0b67
git merge origin/cursor/phase4-chrome-behavior-0b67   # includes 4744fcd9

node parity/scripts/build-refpack-head-overrides.mjs
cd drupal/web && ../vendor/bin/drush updb -y && ../vendor/bin/drush cr

cd parity
DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:head:refpack
DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:head:manifest
DRUPAL_BASE_URL=http://127.0.0.1:8888 URL_PARITY_CONCURRENCY=1 node scripts/check-url-parity.mjs
DRUPAL_BASE_URL=http://127.0.0.1:8888 PARITY_BEHAVIOR_PATHS=auto npm run parity:behavior:templates
```

Updated: 2026-10-29 (post PR #9 base merge, 21-slug refpack)
