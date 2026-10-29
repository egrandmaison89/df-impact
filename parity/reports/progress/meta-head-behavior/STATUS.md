# Meta / head / behavior / URL agent status

Branch: `cursor/meta-head-behavior-0751`  
Base: `cursor/phase4-chrome-behavior-0b67`

## Gates (before → after)

| Gate | Before | After | Notes |
| --- | --- | --- | --- |
| metaMatch (smoke baseline) | 0% | _pending Drupal bootstrap in VM_ | Root cause: OG/Twitter submodules disabled; title suffix `\|` vs `- Impact Magazine`; manual `<title>` bypassing Metatag; empty og tags on nodes. |
| ref-pack head self-test | n/a | run `npm run test -- tests/head-meta-refpack.spec.ts` | Parses 13 reference-pack pages offline. |
| head vs Drupal | n/a | `npm run parity:head:refpack` | Needs `DRUPAL_BASE_URL` + bootstrapped site. |
| behavior assertions | partial spec | extended inventory in `behavior-parity.spec.ts` | Needs Drupal serve for green run. |
| URL parity | unknown | `npm run parity:url:check` | Manifest direct-200 check. |

## Done this branch

- Enabled `metatag_open_graph`, `metatag_twitter_cards`, new `df_metatag` module (production URLs, topic archive titles, homepage bundle, JSON-LD graph).
- Synced Metatag defaults + Views metatags to Yoast strings from `parity/reference-pack/*/desktop/rendered.html`.
- `system.site` name → **Impact Magazine**; removed duplicate `<title>` from `html.html.twig`.
- Harness: `parity/lib/head-meta.mjs`, `compare-head-meta.mjs`, `check-url-parity.mjs`, `head-meta-refpack.spec.ts`.
- Behavior: scroll/header/donate/dropdown/gtranslate/search/reveal assertions; WP chrome scroll threshold 50px.

## Next

1. `./scripts/repro/bootstrap-from-migrations.sh` on DDEV host → `drush serve 127.0.0.1:8888`.
2. `npm run parity:head:refpack` and `npm run parity:smoke:baseline` — target **100% metaMatch**.
3. `npm run parity:behavior:smoke` with `PARITY_BEHAVIOR_STRICT=1` when hero media present.

PR: https://github.com/egrandmaison89/df-impact/pull/6 (branch `cursor/meta-head-behavior-0751` → `cursor/phase4-chrome-behavior-0b67`)

Updated: 2026-10-29 (initial)
