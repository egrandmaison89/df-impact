# Issue landing + listings agent (`cursor/issue-landing-parity-185b`)

**Coordination:** Editor / content sweep agent → branch `cursor/content-sweep-ce7f` (PR #17 area).  
**Mac layout agent:** avoid `node--issue--full.html.twig` / issue PostX twigs — this work is PHP backfills, `df_setup.module` Views alters, browse grid covers, and parity fixtures.

## Deterministic issue backfill (2026-10-29)

| Item | Location |
| --- | --- |
| **Fixture JSON (committed)** | `drupal/web/modules/custom/df_migrate/data/issue_page_wp_fixture.json` |
| **Regenerate** | `node parity/scripts/build-issue-page-fixture.mjs` (optional: refresh `parity/cache/wp-issue-pages/` via `fetch-wp-issue-pages.mjs` first) |
| **Deploy hook** | `df_setup_update_10027()` — runs PostX + issue-page backfills; **reads fixture first**, live WP only if row missing |
| **Replay test** | `drush scr web/modules/custom/df_migrate/scripts/test_issue_fixture_replay.php` (clears banner/cover/promo, replays backfills) |

## Text parity (WP cache vs Drupal, desktop)

| Scope | Result |
| --- | --- |
| **15 comparable issue landings** | **≥ 98.9%** (min on `/spring-2024-issue`); **14 at 100%** |
| **`/late-fall-2022-issue`** | **Non-comparable** — cached/live WP returns Jetpack `arch_err` / empty PostX (no real issue landing HTML). Drupal keeps migrated issue; not on WP `/browse-issues/` grid. |
| **`/browse-issues`** | **100%** vs ref pack (15 covers, WP season order, excludes late fall) |

Latest sweep: `issue-live-sweep-1793263771373.json`

## Editor / content-sweep handoff

- **Browse issues / issue covers:** owned here (fixture `cover_url` → `field_cover_image`, Views sort in `df_setup_views_query_alter`).
- **Article body / editor_html / workflow:** owned on `cursor/content-sweep-ce7f` — do not revert `df_editor` or format filters on merge.
- **Listing pages (ref-pack text, this VM):** `/browse-issues` **100%**; `/category/cancer-research` **100%**; `/in-brief` **100%**; `/category/digital-exclusives` **99%**; `/about-impact` **~97%** (staff roster differs from stale ref — **content-sweep / editor** to refresh copy or ref); `/search` not comparable without shared query string (empty vs template shell).
- **No ref pack yet:** `/category/pediatrics`, `/stories` — track on `cursor/content-sweep-ce7f`.

## Gates (before push)

- `parity:template-samples:http`
- `parity:smoke:200`
