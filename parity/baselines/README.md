# Frozen WordPress baselines

Versioned snapshots of live WordPress (full-page PNG, main-content text, meta) for parity comparison **without** hitting `danafarberimpact.org` from CI or datacenter IPs.

## Layout

```
baselines/<PARITY_BASELINE_ID>/
  baseline-meta.json
  <url-slug>/
    desktop|tablet|mobile/
      wordpress.png
      wordpress.json
```

Baseline directories are gitignored by default (large binaries). Teams may commit a baseline tarball or store it in shared storage; keep **no cookies or credentials** in the repo.

## Capture (owner Mac / residential IP)

From the repo root:

```bash
cd parity
npm ci
npx playwright install chromium
export PARITY_BASELINE_ID="df-impact-$(date +%Y-%m-%d)"
# Smoke subset (~25 URLs):
npm run parity:capture-baseline:smoke
# Full content manifest (~738 URLs):
PARITY_SAMPLE=0 npm run parity:capture-baseline
```

Optional: `WP_BASE_URL=https://danafarberimpact.org` (default).

## Compare Drupal against baseline

With Drupal at `http://127.0.0.1:8888`:

```bash
cd parity
PARITY_WP_BASELINE="df-impact-2026-10-28" npm run parity:smoke
PARITY_WP_BASELINE="df-impact-2026-10-28" npm run parity
```

See [docs/FROZEN_WP_BASELINE.md](../../docs/FROZEN_WP_BASELINE.md) for full details.
