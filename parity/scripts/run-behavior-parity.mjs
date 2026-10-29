#!/usr/bin/env node
import { spawnSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');

process.env.PARITY_ROOT = parityRoot;
if (!process.env.PARITY_BEHAVIOR_RUN_ID) {
  process.env.PARITY_BEHAVIOR_RUN_ID = new Date().toISOString().replace(/[:.]/g, '-');
}

console.log('Behavior parity run:', process.env.PARITY_BEHAVIOR_RUN_ID);
console.log('WP:', process.env.WP_BASE_URL || 'https://danafarberimpact.org');
console.log('Drupal:', process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888');
if (process.env.PARITY_WP_BASELINE) {
  console.log('Baseline id:', process.env.PARITY_WP_BASELINE, '(set PARITY_BEHAVIOR_HIT_WP=1 to still hit live WP for video)');
}

const pw = spawnSync(
  'npx',
  ['playwright', 'test', '--project=behavior'],
  { cwd: parityRoot, stdio: 'inherit', env: { ...process.env, PARITY_ROOT: parityRoot } },
);

if ((pw.status ?? 1) === 0 && process.env.PARITY_BEHAVIOR_CROSS_SITE === '1') {
  spawnSync('node', ['scripts/summarize-behavior-wp-diff.mjs', process.env.PARITY_BEHAVIOR_RUN_ID], {
    cwd: parityRoot,
    stdio: 'inherit',
  });
}

process.exit(pw.status ?? 1);
