#!/usr/bin/env node
/** All template sample paths × desktop hover parity (serial). */
import { spawnSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const runId = process.env.REPORT_RUN_ID || `hover-cs-full-${Date.now()}`;

const r = spawnSync('node', ['scripts/computed-style-hover-diff.mjs'], {
  cwd: parityRoot,
  env: { ...process.env, REPORT_RUN_ID: runId, PARITY_SERIAL: '1' },
  encoding: 'utf8',
  stdio: 'inherit',
});
process.exit(r.status ?? 1);
