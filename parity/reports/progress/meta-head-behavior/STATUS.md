# Meta / head / behavior / URL agent status

Branch: `cursor/meta-head-behavior-0751`  
PR: https://github.com/egrandmaison89/df-impact/pull/10  
Base: `cursor/phase4-chrome-behavior-0b67` (PR #9 merged; watch open **PR #5** on same base for WXR chrome commits)

Drupal (VM): `http://127.0.0.1:8888`

## Resume checklist (after context summarization)

1. `git pull origin cursor/meta-head-behavior-0751`
2. `cd drupal/web && ../vendor/bin/drush cr`
3. Head: `cd parity && DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:head:manifest` → expect **773/773**
4. Cross-site behavior: `npm run parity:behavior:smoke` then `npm run parity:behavior:templates`
5. Aggregate diff: `npm run parity:behavior:report-diff` → `reports/behavior/<runId>/wp-drupal-behavior-summary.json`
6. Fix remaining mismatches in `drupal/web/themes/custom/df_impact/js/wp-chrome-behaviors.js`, `css/drupal-overrides.css`, `js/scroll-reveal.js`

## Gates (VM)

| Gate | Result | Notes |
| --- | --- | --- |
| `parity:head:manifest` | **773/773 (100%)** | Latest `head-meta/manifest-1793243108644.json` |
| `parity:head:refpack` (21) | **21/21** | `compare-1793242022721.json` |
| `parity:url:check` | **801/801** | Use `URL_PARITY_CONCURRENCY=1` on Mac if undici crashes |
| Migrations | **439 / 323 / 853** | media map rows |
| `parity:behavior:templates` (Drupal-only, prior) | 48/48, 588 assertions | `2026-10-29T02-48-56-280Z` |
| **WP ↔ Drupal behavior (new)** | In progress | Live WP + Drupal Playwright; see below |

## WP vs Drupal dynamic behavior parity (overnight work)

**Goal:** Same measurements on live WordPress and Drupal, diff report, fix theme JS/CSS.

**Harness**

- `parity/lib/behavior-profiles.mjs` — per-site selectors + `captureSiteBehavior()`
- `parity/lib/behavior-compare.mjs` — video/style/dropdown/scroll diffs + `buildCrossSiteReport()`
- `parity/tests/behavior-parity.spec.ts` — cross-site when `PARITY_BEHAVIOR_CROSS_SITE=1`
- `parity/scripts/summarize-behavior-wp-diff.mjs` — roll up `wp-drupal-behavior-diff.json`
- Per case: `reports/behavior/<runId>/compare/<slug>_<bp>/wp-drupal-behavior-diff.json`

**npm scripts**

```bash
npm run parity:behavior:smoke          # /homepage, cross-site
npm run parity:behavior:templates      # auto paths, cross-site (hits live WP)
npm run parity:behavior:templates:drupal-only
npm run parity:behavior:report-diff
```

**Measured:** hero video attrs/src, header scroll class, nav/donate/footer/body/card/button hover computed styles, mega-menu dropdown on hover, mobile menu toggle, scroll-reveal counts, carousel/accordion inventory.

**Theme fixes this pass**

- `wp-chrome-behaviors.js`: hero `play()` when autoplay blocked; scroll → `.scrolled` + `.header--scrolled`; removed inline `mega-sub-menu { display:none }`
- `drupal-overrides.css`: sticky header, mega flyout `:hover`, footer link hover color, scrolled shadow

**Mac DDEV:** Point `DRUPAL_BASE_URL` at DDEV; WP stays `https://danafarberimpact.org`. Full template run is slow (~25min+ VM) due to live WP loads.

## Manifest head method

`articles.json` / `in_brief.json` / `wp_head_overrides.json` / ref-pack HTML → `parity:head:manifest`. Mac 738-URL run was ~14.5% before overrides + metatag fixes; VM now **100%** on 773 with expectations.

Updated: 2026-10-29T03:08Z
