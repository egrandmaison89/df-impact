#!/usr/bin/env node
/**
 * Run all 48 template behavior cases in three breakpoint passes (same runId).
 */
import { spawnSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const parityRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const runId = process.env.PARITY_BEHAVIOR_RUN_ID || new Date().toISOString().replace(/[:.]/g, '-');

const baseEnv = {
  ...process.env,
  PARITY_ROOT: parityRoot,
  PARITY_BEHAVIOR_RUN_ID: runId,
  PARITY_BEHAVIOR_PATHS: process.env.PARITY_BEHAVIOR_PATHS || 'auto',
  PARITY_BEHAVIOR_HIT_WP: '1',
  PARITY_BEHAVIOR_CROSS_SITE: '1',
};

function runPass(label) {
  console.log('\n=== Behavior pass:', label, 'runId:', runId, '===\n');
  const r = spawnSync('node', ['scripts/run-behavior-parity.mjs'], {
    cwd: parityRoot,
    stdio: 'inherit',
    env: { ...baseEnv, PARITY_BEHAVIOR_BREAKPOINT: label },
  });
  return r.status ?? 1;
}

for (const label of ['desktop', 'tablet', 'mobile']) {
  runPass(label);
}

const sum = spawnSync('node', ['scripts/summarize-behavior-wp-diff.mjs', runId], {
  cwd: parityRoot,
  stdio: 'inherit',
});
process.exit(sum.status ?? 1);
