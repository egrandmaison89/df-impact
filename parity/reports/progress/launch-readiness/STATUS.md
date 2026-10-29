# Launch readiness (non-visual parity)

Branch: `cursor/launch-readiness-ce7f` · [PR #19](https://github.com/egrandmaison89/df-impact/pull/19). Docs: [`docs/deploy/ACQUIA_RUNBOOK.md`](../../../../docs/deploy/ACQUIA_RUNBOOK.md).

## Performance (Lighthouse)

**Command:** `LAUNCH_WP_SOURCE=live WP_BASE_URL=https://danafarberimpact.org DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:launch:lighthouse` (retries via `LAUNCH_LIGHTHOUSE_RETRIES`, default 5).

**Artifact:** `lighthouse-1793258053079.json` · Live WP + local Drupal VM.

| Template | FF | Drupal perf | Drupal LCP | Drupal CLS | Drupal TBT | Drupal KB / req | WP perf | WP LCP | WP CLS | WP TBT | WP KB / req |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| homepage | mobile | 0.55 | 31.0s | 0.008 | 19ms | 5614 / 51 | 0.91* | 1.7s* | 0.19* | 0* | 9 / 12* |
| homepage | desktop | 0.55 | 31.0s | 0.001 | 20ms | 5614 / 51 | 0.90* | 1.7s* | 0.10* | 0* | 9 / 12* |
| issue | mobile | 0.55 | 31.4s | **0.001** | 17ms | 5674 / 54 | 0.68 | 10.5s | 0.007 | 44ms | 1538 / 87 |
| issue | desktop | 0.55 | 31.2s | 0.001 | 19ms | 5674 / 54 | 0.54 | 8.9s | 0.039 | 128ms | 1600 / 89 |
| article | mobile | 0.55 | 30.6s | 0.021 | 19ms | 5600 / 48 | 0.91* | 1.7s* | 0.19* | 0* | 9 / 12* |
| article | desktop | 0.55 | 30.6s | 0.018 | 21ms | 5600 / 48 | 0.90* | 1.7s* | 0.10* | 0* | 9 / 12* |
| archive | mobile | 0.55 | 31.0s | 0.002 | 18ms | 5638 / 56 | 0.73 | 5.7s | 0.001 | 31ms | 2003 / 74 |
| archive | desktop | 0.55 | 32.5s | 0.003 | 21ms | 5817 / 60 | 0.59 | 5.6s | 0.005 | 46ms | 2003 / 74 |

\* **WP egress / bot wall:** Some live URLs return a minimal challenge document (~9 KB, ~12 requests). Treat **issue** and **archive** WP rows as the reliable live baseline; re-run from a network that receives full HTML for homepage/article before cutover sign-off.

**Local VM LCP** is dominated by hero video and throttling; compare trends on Acquia prod, not absolute seconds.

### Issue mobile CLS (before / after)

| Stage | CLS (issue mobile) | Change |
| --- | --- | --- |
| Before PostX reserve (`lighthouse-1793257578383.json`) | **0.475** | — |
| After aspect-ratio on `.ultp-block-70af6c .ultp-block-image` + banner reserve | **0.001** | PostX lazy cards were collapsing before load (axe target: `ultp-block-content`) |

## Accessibility (axe)

| Template | Drupal | WP (ref-pack) | Δ |
| --- | --- | --- | --- |
| homepage / issue / article / archive | 2 each | 2 each | 0 |

**Artifact:** `axe-1793257756047.json`

## Analytics & tracking (`df_launch`)

| Tag | WordPress (reference-pack) | Drupal (`df_launch`) | Fires |
| --- | --- | --- | --- |
| Adobe Launch | `launch-90664c3f0ed7.min.js` | Same URL in config | Page load (prod only) |
| OneTrust | `otSDKStub.js` + domain `e2254d3c-…` | Matched | Consent (prod only) |
| Meta Pixel | **340424612793527** | `fbq` bootstrap | PageView (prod only) |
| Bing UET | `bat.bing.com/bat.js` | Optional include | UET (prod only) |
| Jetpack stats | blog **212495365** | `_wpStats` bootstrap | Legacy parity (prod only) |

**Production gate:** `LaunchEnvironment` requires `df_launch.settings:enabled` **and** (`AH_SITE_ENVIRONMENT=prod` or `$settings['df_launch_tags_enabled'] = TRUE`, default **off**). Sync ships `enabled: false`; Acquia prod sets enabled via settings include (see runbook). **Unit tests:** `npm run parity:launch:tags-test` or PHPUnit path in runbook.

## Gates

| Gate | Result | Artifact |
| --- | --- | --- |
| `parity:smoke:200` | pass | `smoke-1793257134209.json` |
| `parity:head:refpack` | 21/21 | `compare-1793257134869.json` |
| `parity:url:check` | 801/801 | `check-1793257196450.json` |
| `parity:launch:axe` | 0 regressions | `axe-1793257756047.json` |
| `parity:launch:lighthouse` | live WP + Drupal | `lighthouse-1793258053079.json` |
| `parity:launch:tags-test` | 5/5 PHPUnit | `df_launch` unit tests |
