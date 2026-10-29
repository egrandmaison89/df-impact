# Meta / head / behavior / URL agent status

Branch: `cursor/meta-head-behavior-0751`  
Base: `cursor/phase4-chrome-behavior-0b67`  
Drupal (VM): `http://127.0.0.1:8888` (`drush serve`, MariaDB `dfimpact`)

## Gates (before → after, verified on VM 2026-10-29)

| Gate | Before | After | Notes |
| --- | --- | --- | --- |
| `parity:head:refpack` (13 ref-pack paths) | 0% (0/13) → 92.3% (12/13) | **100% (13/13)** | Fixed category archive: slug lookup, `<title>`, `og:url`, `og:type`; canonical + JSON-LD via `wp_head_overrides.json` + harness |
| ref-pack offline tests | n/a | **13/13** | `npm run test -- tests/head-meta-refpack.spec.ts` |
| `parity:behavior:smoke` (`/homepage`) | not run | **3/3 pass** | wp-chrome selectors, hero fallback MP4, reduced-motion pause |
| `parity:url:check` (801 manifest URLs) | not run | **17.9% (143/801)** | **Blocker:** 658 Drupal **404** — articles not migrated (partial `df_articles` import) |
| `parity:smoke:baseline` metaMatch | 0% (harness) | not run | `parity/baselines/` empty in VM — no `df-impact-2026-10-28` lite package; use ref-pack head gate + owner Mac baseline |

## Harness

- `metaMatches`: title, description, og:title/description/image, **canonical**, **jsonLd** (`parity/lib/thresholds.mjs`).
- `pickMetaFromHtml`: entity decode; `content="..."` regex for apostrophes (`parity/lib/head-meta.mjs`).

## Drupal fixes (this verification pass)

- `df_metatag`: production URLs, overrides JSON, topic archive term via `df_setup_topic_term_load_by_category_slug`, `hook_preprocess_html` for document title, sync `og:url` with canonical.
- Theme: category `<title>` suffix; homepage hero WP fallback video; `wp-chrome-behaviors.js` pauses hero when `prefers-reduced-motion: reduce`.
- Behavior spec: wp-chrome selectors (`.desktop-header`, `.mega-menu-link`, etc.).

## Proven URL parity blocker

```text
658 / 801 failures → Drupal HTTP 404 (sample: /2023/07/* article paths)
```

Completing `df_migrate` article migration (or manifest scoped to imported nodes) is required before **100% URL parity**.

## Commands (repro)

```bash
cd drupal/web && ../vendor/bin/drush serve 127.0.0.1:8888
cd parity && DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:head:refpack
cd parity && DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:behavior:smoke
cd parity && DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:url:check
```

PR: https://github.com/egrandmaison89/df-impact/pull/6

Updated: 2026-10-29 (VM verification)
