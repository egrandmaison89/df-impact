#!/usr/bin/env node
/**
 * One-command parity run: Playwright compare + JSON aggregate + HTML report.
 */
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const repoRoot = path.resolve(parityRoot, '..');

const runId = process.env.PARITY_RUN_ID || new Date().toISOString().replace(/[:.]/g, '-');
process.env.PARITY_RUN_ID = runId;

const manifest = path.join(parityRoot, 'manifest', 'site-manifest.json');
if (!fs.existsSync(manifest)) {
  console.error('Missing manifest. Run: npm run manifest');
  process.exit(1);
}

console.log(`Parity run ${runId}`);
console.log(`WP_BASE_URL=${process.env.WP_BASE_URL || 'https://danafarberimpact.org'}`);
console.log(`DRUPAL_BASE_URL=${process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888'}`);
if (process.env.PARITY_WP_BASELINE) {
  console.log(`PARITY_WP_BASELINE=${process.env.PARITY_WP_BASELINE} (frozen WP reference — no live WP fetches)`);
} else if (process.env.PARITY_REQUIRE_BASELINE === '1') {
  console.error('PARITY_REQUIRE_BASELINE=1 but PARITY_WP_BASELINE is unset. Use owner baseline df-impact-2026-10-28.');
  process.exit(1);
} else {
  console.warn('Live WordPress reference (captcha-noisy). Set PARITY_WP_BASELINE=df-impact-2026-10-28 for release scores.');
}

const pw = spawnSync(
  'npx',
  ['playwright', 'test', '--project=parity'],
  {
    cwd: parityRoot,
    stdio: 'inherit',
    env: { ...process.env, PARITY_RUN_ID: runId },
  },
);

const agg = spawnSync('node', ['scripts/aggregate-results.mjs', runId], {
  cwd: parityRoot,
  stdio: 'inherit',
});

const html = spawnSync('node', ['scripts/generate-html-report.mjs', runId], {
  cwd: parityRoot,
  stdio: 'inherit',
});

const gapArgs = ['scripts/generate_gap_report.py', '--run-id', runId];
if (process.env.PARITY_BASELINE_RUN_ID) {
  gapArgs.push('--baseline-run-id', process.env.PARITY_BASELINE_RUN_ID);
}
const gap = spawnSync('python3', gapArgs, {
  cwd: parityRoot,
  stdio: 'inherit',
});

const evalThresholds = spawnSync('node', ['scripts/evaluate-thresholds.mjs', runId], {
  cwd: parityRoot,
  stdio: 'inherit',
});

process.exit(
  pw.status || agg.status || html.status || gap.status || evalThresholds.status || 0,
);
