#!/usr/bin/env node
/** Live WP capture for every path in parity/config/template-samples.json (1440/768/375). */
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const samples = JSON.parse(
  fs.readFileSync(path.join(parityRoot, 'config', 'template-samples.json'), 'utf8'),
);
const paths = [...new Set(Object.values(samples).flatMap((e) => e.paths || []))];
const env = {
  ...process.env,
  PACK_DIR: process.env.PACK_DIR || 'reference-pack',
  PACK_PATHS: paths.join(','),
};
const r = spawnSync('node', ['scripts/capture-reference-pack.mjs'], {
  cwd: parityRoot,
  env,
  stdio: 'inherit',
});
process.exit(r.status ?? 1);
