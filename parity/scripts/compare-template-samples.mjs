#!/usr/bin/env node
/** Run Mac-pack compare for one template's sample paths (parity/config/template-samples.json). */
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const template = process.env.PARITY_TEMPLATE;
if (!template) {
  console.error('Set PARITY_TEMPLATE=homepage|issue|article|in_brief|landing_page|archive');
  process.exit(1);
}
const samples = JSON.parse(
  fs.readFileSync(path.join(parityRoot, 'config', 'template-samples.json'), 'utf8'),
);
const entry = samples[template];
if (!entry?.paths?.length) {
  console.error(`No paths for template ${template}`);
  process.exit(1);
}
const env = {
  ...process.env,
  PACK_COMPARE_PATHS: entry.paths.join(','),
  PROGRESS_COMPONENT: process.env.PROGRESS_COMPONENT || template,
};
const r = spawnSync('node', ['scripts/compare-reference-pack.mjs'], {
  cwd: parityRoot,
  env,
  stdio: 'inherit',
});
process.exit(r.status ?? 1);
