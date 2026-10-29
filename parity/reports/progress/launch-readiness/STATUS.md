# Launch readiness (non-visual parity)

Branch: `cursor/launch-readiness-ce7f` (stack on [PR #18](https://github.com/egrandmaison89/df-impact/pull/18)). Docs: [`docs/deploy/ACQUIA_RUNBOOK.md`](../../../../docs/deploy/ACQUIA_RUNBOOK.md).

## Performance (Lighthouse)

**Templates:** homepage, issue, article, archive — mobile + desktop (`parity/config/launch-templates.json`).

| Template | Form factor | Drupal (VM) | WP baseline | Notes |
| --- | --- | --- | --- | --- |
| homepage | mobile | perf 0.54 · LCP 35.6s · CLS 0.008 · TBT 113ms · 6126 KB · 73 req | ref-pack static N/A | |
| homepage | desktop | perf 0.55 · LCP 33.7s · CLS 0.001 · TBT 54ms · 6126 KB · 73 req | ref-pack static N/A | Hero video dominates LCP locally |
| issue | mobile | perf 0.35 · CLS **0.47** · 6280 KB · 76 req | ref-pack N/A | CLS from cover/banner layout (match WP ref layout) |
| issue | desktop | perf 0.40 · CLS 0.28 · 6187 KB · 76 req | ref-pack N/A | |
| article | mobile | perf 0.55 · LCP 33.6s · CLS 0.021 · 6112 KB · 70 req | ref-pack N/A | |
| article | desktop | perf 0.55 · LCP ~33s · CLS ~0.02 · ~6112 KB · 70 req | ref-pack N/A | |
| archive | mobile/desktop | perf ~0.54 · LCP ~35s · CLS ~0.003 · ~6150 KB | ref-pack N/A | |

**Artifact:** `lighthouse-1793256935494.json`

**WP comparison note:** Reference-pack `rendered.html` files are static snapshots; Lighthouse cannot score them as navigable origins (metrics null). For launch sign-off, re-run with `LAUNCH_WP_SOURCE=live` and `WP_BASE_URL=https://danafarberimpact.org` when bot wall allows, or use Chrome DevTools on paired URLs.

**Drupal mitigations shipped this workstream:**

| Area | Change |
| --- | --- |
| Images | PostX cards: `loading="lazy"`, Photon **srcset** (350w + 768w) via `postx-card-image.html.twig` |
| Hero video | `preload="metadata"` on homepage cover video |
| CSS/JS | `system.performance` preprocess + gzip enabled (full aggregation on Acquia prod) |
| Fonts | Google Fonts URL already uses `display=swap` (`css/base.css`) |
| Markup | Removed duplicate `<title>` in `html.html.twig` |
| PHP | Fixed `preg_match` delimiter in article image fallback (smoke watchdog clean) |

## Accessibility (axe)

| Template | Drupal violations | WP (ref-pack) violations | Δ |
| --- | --- | --- | --- |
| homepage | 2 (`aria-allowed-attr`, `link-name`) | 2 (`document-title`, `html-has-lang`) | 0 |
| issue | 2 (`aria-allowed-attr`, `link-name`) | 2 | 0 |
| article | 2 | 2 | 0 |
| archive | 2 | 2 | 0 |

**Artifact:** `axe-1793256663981.json` · Script: `npm run parity:launch:axe`

Fixes: pager ellipsis `role="presentation"` removed (axe `list` on issue view). Shared chrome violations match mega-menu/social patterns from WP vendored assets; ref-pack HTML lacks `<html lang>` so WP counts differ.

## Analytics & tracking

WP does **not** expose a standalone GTM container on homepage reference HTML; primary tag management is **Adobe Launch** (includes GA/Adobe Analytics rules).

| Tag | WordPress (reference-pack) | Drupal (`df_launch`) | Fires |
| --- | --- | --- | --- |
| Adobe Launch | `launch-90664c3f0ed7.min.js` | Same URL in `df_launch.settings` | Page load + `_satellite` rules (container-managed) |
| OneTrust | `otSDKStub.js` + domain `e2254d3c-…` | Matched in `df_launch` | Consent banner |
| Meta Pixel | ID **340424612793527** | `fbq('init')` + `PageView` | Page view |
| Bing UET | `bat.bing.com/bat.js` | Optional script include | Container / UET |
| Jetpack stats | `pixel.wp.com/g.gif` blog **212495365** | `_wpStats` bootstrap | Page metrics (legacy WP parity) |
| appEventData / link tracking | Inline `_satellite` + click listener | Via Adobe Launch + theme behaviors | Outbound / CTA clicks (Launch-managed) |

**Not duplicated in Drupal core:** standalone `google_tag` / `google_analytics` modules (account empty) — use Launch + `df_site_snippets` for any extra containers.

## Gates (before push)

| Gate | Result | Artifact |
| --- | --- | --- |
| `parity:smoke:200` | pass (39 paths, 0×5xx, 0 new PHP watchdog) | `smoke-1793256585884.json` |
| `parity:head:refpack` | pass 21/21 | `compare-1793256596728.json` |
| `parity:url:check` | pass 801/801 | `check-1793256653931.json` |
| `parity:launch:axe` | pass (0 regressions) | `axe-1793256663981.json` |
| `parity:launch:lighthouse` | captured (Drupal only vs ref-pack) | `lighthouse-1793256935494.json` |

```bash
cd parity
DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:smoke:200
DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:head:refpack
DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:url:check
npm run parity:launch:axe
npm run parity:launch:lighthouse
```
