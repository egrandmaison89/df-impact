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

const gap = spawnSync('python3', ['scripts/generate_gap_report.py', '--run-id', runId], {
  cwd: parityRoot,
  stdio: 'inherit',
});

process.exit(pw.status || agg.status || html.status || gap.status || 0);
