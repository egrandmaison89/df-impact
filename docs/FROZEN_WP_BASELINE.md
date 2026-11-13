# Frozen WordPress baseline mode

SiteGround often serves a **captcha** to cloud/CI browsers, which makes live WordPress comparisons unreliable. Baseline mode separates:

1. **Capture** — record WordPress reference data from a normal browser environment (e.g. owner Mac on a residential IP). **No WordPress login or cookies** are stored or required.
2. **Compare** — run the parity harness against Drupal only, diffing pixels/text/meta against the frozen baseline.

## Capture on macOS

Prerequisites: Node 18+, Git clone of this repo.

```bash
cd /path/to/df-impact/parity
npm ci
npx playwright install chromium
```

Choose a baseline id (date-stamped):

```bash
export PARITY_BASELINE_ID="df-impact-$(date +%Y-%m-%d)"
```

**Smoke capture** (~25 content URLs, all templates):

```bash
npm run parity:capture-baseline:smoke
```

**Full content manifest** (~738 URLs):

```bash
PARITY_SAMPLE=0 npm run parity:capture-baseline
```

Output: `parity/baselines/$PARITY_BASELINE_ID/`. Do **not** add cookies or credentials.

Verify `baseline-meta.json` and spot-check `wordpress.json` files for `"wpCaptcha": false`.

### Sharing baselines (full captures are ~3.5 GB)

Do **not** commit full baselines to plain git. Recommended options:

| Approach | Best for |
| --- | --- |
| **Lite zip (JSON only)** | Cloud agents / CI text & meta gates (~tens of MB) |
| **Full folder on owner Mac** | Pixel diff at full fidelity (2% / 5% gates) |
| **Cloud drive / release asset** | Team handoff of full `baselines/<id>/` |
| **Git LFS** | Optional if the org already uses LFS for large artifacts |

**Lite bundle** (from `parity/`, after capture):

```bash
export PARITY_BASELINE_ID="df-impact-2026-10-28"
node scripts/package-baseline-lite.mjs
# → baselines/df-impact-2026-10-28-lite.zip (wordpress.json + baseline-meta + manifest-lite)
```

Parity with `PARITY_WP_BASELINE` uses `wordpress.json` for text/meta; **pixel diff needs `wordpress.png`** on the machine running Playwright. Cloud agents can consume the lite zip for content/meta thresholds; the owner runs pixel comparison locally until a downscaled-PNG bundle or shared storage is agreed.

**Playwright note:** Baseline capture specs resolve paths via `lib/parity-root.mjs` (no `import.meta` in the spec), so capture works on owner Mac (Node 24, CJS `package.json`) and in ESM `.mjs` runners alike.

## Compare (CI, cloud agent, or local)

Start Drupal (see [REPRODUCIBLE_ENVIRONMENT.md](./REPRODUCIBLE_ENVIRONMENT.md)), then:

```bash
cd parity
export PARITY_WP_BASELINE="df-impact-2026-10-28"   # match your baseline id
export DRUPAL_BASE_URL="http://127.0.0.1:8888"
npm run parity:smoke    # or npm run parity for full manifest
```

Live WordPress is **not** contacted when `PARITY_WP_BASELINE` is set (static parity). **Release scores** must use the owner baseline (`df-impact-2026-10-28`): `npm run parity:smoke:baseline` or `PARITY_REQUIRE_BASELINE=1`. **Behavior tests** may still use live WP for video playback — set `PARITY_BEHAVIOR_HIT_WP=1` on the owner Mac.

## Acceptance criteria

Thresholds in [ACCEPTANCE_CRITERIA.md](./ACCEPTANCE_CRITERIA.md) apply the same way; baseline mode only changes the WordPress reference source.
