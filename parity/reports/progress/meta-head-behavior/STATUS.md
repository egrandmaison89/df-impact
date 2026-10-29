# Meta / head / behavior / URL agent status

Branch: `cursor/meta-head-behavior-0751`  
Base: `cursor/phase4-chrome-behavior-0b67`  
PR: https://github.com/egrandmaison89/df-impact/pull/9  
Drupal (VM): `http://127.0.0.1:8888` (`drush serve`, MariaDB `dfimpact`)

## Gates (VM verification 2026-10-29)

| Gate | Result | Notes |
| --- | --- | --- |
| Migrations `df_articles` | **439 / 439** | Idle |
| Migrations `df_in_brief` | **323 / 323** | Idle |
| Migrations `df_media_images` | **848 / 853** | 5 sources use placeholder/fallback when remote mirror unavailable; `migrate_map` has 853 rows, 0 NULL dest |
| `drush df-migrate:sync-wordpress-paths` | **Done** | 439 articles + 323 in-brief aliases refreshed |
| `parity:url:check` (801 URLs) | **100% (801/801)** | Report `parity/reports/url-parity/check-1793239889622.json`; concurrent Drupal checks + WP only on non-200 |
| `parity:head:refpack` (13 paths) | **100% (13/13)** | Report `head-meta/compare-1793239902185.json` |
| `parity:head:manifest` (772 w/ expected) | **100% (772/772)** | Method below; report `head-meta/manifest-1793240054920.json` |
| `parity:behavior:smoke` (`/homepage`) | **3/3 tests, 48/48 assertions** | `reports/behavior/2026-10-29T02-smoke3/run-meta.json` |
| `parity:behavior:templates` (`PARITY_BEHAVIOR_PATHS=auto`) | **45/45 tests** | **552/552 assertions** — `reports/behavior/2026-10-29T02-parity/run-meta.json` (15 template paths × 3 breakpoints) |
| `parity:smoke:baseline` | **Blocked (proven)** | No `parity/baselines/df-impact-2026-10-28` lite package in VM — use ref-pack + manifest head gates |

### Behavior assertion reporting

- Smoke and template runs write per-case `behavior.json` with named inventory assertions (`hero.*`, `header.*`, `nav.hover`, `donate.cta`, `chrome.gtranslate`, `mobile.drawer`, etc.).
- `run-meta.json` includes `assertionSummary`: `{ tests, assertions, passed, passRate }` (not just test pass count).

## Manifest head/meta method (no frozen baseline required)

Documented in `parity/lib/wp-expected-meta.mjs` and `npm run parity:head:manifest`:

1. **articles.json / in_brief.json** — Yoast-style title (suffix ` - Impact Magazine`), description (`meta_description` or `excerpt`), canonical (`wp_url`), featured image URL.
2. **wp_head_overrides.json** — production canonicals (incl. external defycancer), JSON-LD graphs, archive/issue/home overrides.
3. **reference-pack** desktop `rendered.html` — paths not in JSON exports.
4. **Comparison** — `pickMetaFromHtml` on live Drupal HTML vs expected; `metaMatches` in `parity/lib/thresholds.mjs` (entity decode, strip HTML tags, skip empty expected fields, optional JSON-LD).

Drupal **`WordPressHeadOverrides`** mirrors JSON paths; `/about` → `/about-impact`, `/home` → `/homepage` alias merge for metatags + JSON-LD.

## Key fixes this pass

- URL parity harness: batched concurrency; skip live WP unless Drupal non-200.
- Meta: excerpt fallback when `meta_description` empty; path aliases for legacy `/about`, `/home`.
- Migrate: media placeholder PNG when download/mirror fails; Pediatrics topic in `topics.json` + `df_setup_update_10025`.
- Behavior: wp-chrome donate selector; `scroll_reveal.inventory` uses `reveal.count`.

## Commands (repro)

```bash
cd drupal/web && ../vendor/bin/drush serve 127.0.0.1:8888
/workspace/scripts/repro/run-migrations-batch.sh
cd drupal/web && ../vendor/bin/drush df-migrate:sync-wordpress-paths && ../vendor/bin/drush cr

cd parity
DRUPAL_BASE_URL=http://127.0.0.1:8888 URL_PARITY_DRUPAL_ONLY=1 npm run parity:url:check
DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:head:refpack
DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:head:manifest
DRUPAL_BASE_URL=http://127.0.0.1:8888 PARITY_BEHAVIOR_PATHS=/homepage npm run parity:behavior:smoke
DRUPAL_BASE_URL=http://127.0.0.1:8888 PARITY_BEHAVIOR_PATHS=auto npm run parity:behavior:templates
```

Updated: 2026-10-29 (VM verification)
