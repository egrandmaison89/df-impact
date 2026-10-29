#!/usr/bin/env node
/**
 * Content sweep gate for 16 issue landing URLs (PR #22+ integrator).
 * Wraps run-content-sweep.mjs with manifest issue paths.
 */
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const manifest = JSON.parse(
  fs.readFileSync(path.join(parityRoot, 'manifest/site-manifest.json'), 'utf8'),
);
const issuePaths = (manifest.urls || [])
  .filter((e) => e.template === 'issue')
  .map((e) => e.path)
  .filter((p) => p && !/challenge/i.test(p));

if (issuePaths.length < 10) {
  console.error('Expected ~16 issue paths, got', issuePaths.length);
  process.exit(1);
}

const env = {
  ...process.env,
  CONTENT_SWEEP_PATHS: issuePaths.join(','),
};
const r = spawnSync('node', ['scripts/run-content-sweep.mjs'], {
  cwd: parityRoot,
  env,
  stdio: 'inherit',
});
process.exit(r.status ?? 1);
