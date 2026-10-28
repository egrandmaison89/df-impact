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

Output: `parity/baselines/$PARITY_BASELINE_ID/`. Share that folder with the team (zip, cloud drive, optional git LFS). Do **not** add cookies or credentials.

Verify `baseline-meta.json` and spot-check `wordpress.json` files for `"wpCaptcha": false`.

## Compare (CI, cloud agent, or local)

Start Drupal (see [REPRODUCIBLE_ENVIRONMENT.md](./REPRODUCIBLE_ENVIRONMENT.md)), then:

```bash
cd parity
export PARITY_WP_BASELINE="df-impact-2026-10-28"   # match your baseline id
export DRUPAL_BASE_URL="http://127.0.0.1:8888"
npm run parity:smoke    # or npm run parity for full manifest
```

Live WordPress is **not** contacted when `PARITY_WP_BASELINE` is set.

## Acceptance criteria

Thresholds in [ACCEPTANCE_CRITERIA.md](./ACCEPTANCE_CRITERIA.md) apply the same way; baseline mode only changes the WordPress reference source.
