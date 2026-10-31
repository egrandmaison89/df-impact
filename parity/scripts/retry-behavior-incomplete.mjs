#!/usr/bin/env node
/**
 * Re-run behavior parity for incomplete cases from a prior run (same runId merge).
 *
 * Usage:
 *   PARITY_BEHAVIOR_RUN_ID=<id> node scripts/retry-behavior-incomplete.mjs
 */
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { resolveParityRoot } from '../lib/parity-root.mjs';

const parityRoot = resolveParityRoot();
const runId = process.env.PARITY_BEHAVIOR_RUN_ID;
if (!runId) {
  console.error('Set PARITY_BEHAVIOR_RUN_ID to the run folder to merge into.');
  process.exit(1);
}

const summaryPath = path.join(parityRoot, 'reports/behavior', runId, 'wp-drupal-behavior-summary.json');
let incomplete = [];
if (fs.existsSync(summaryPath)) {
  const summary = JSON.parse(fs.readFileSync(summaryPath, 'utf8'));
  incomplete = summary.incompleteCases || [];
} else {
  const compareRoot = path.join(parityRoot, 'reports/behavior', runId, 'compare');
  for (const slug of fs.readdirSync(compareRoot)) {
    const behaviorFile = path.join(compareRoot, slug, 'behavior.json');
    if (!fs.existsSync(behaviorFile)) continue;
    const b = JSON.parse(fs.readFileSync(behaviorFile, 'utf8'));
    if (b.captured === false || !fs.existsSync(path.join(compareRoot, slug, 'wp-drupal-behavior-diff.json'))) {
      incomplete.push({ path: b.path, breakpoint: b.breakpoint, slug });
    }
  }
}

if (!incomplete.length) {
  console.log('No incomplete cases for run', runId);
  process.exit(0);
}

const paths = [...new Set(incomplete.map((c) => c.path))];
console.log('Retrying', incomplete.length, 'incomplete case(s) across', paths.length, 'path(s):', paths.join(', '));

const env = {
  ...process.env,
  PARITY_BEHAVIOR_RUN_ID: runId,
  PARITY_BEHAVIOR_PATHS: paths.join(','),
  PARITY_BEHAVIOR_HIT_WP: '1',
  PARITY_BEHAVIOR_CROSS_SITE: '1',
};

const pw = spawnSync('npx', ['playwright', 'test', '--project=behavior', '--max-failures=9999'], {
  cwd: parityRoot,
  stdio: 'inherit',
  env,
});

const sum = spawnSync('node', ['scripts/summarize-behavior-wp-diff.mjs', runId], {
  cwd: parityRoot,
  stdio: 'inherit',
});

process.exit(Math.max(pw.status ?? 1, sum.status ?? 0));
