#!/usr/bin/env node
/**
 * Capture frozen WordPress baseline (screenshots + main content + meta).
 * Requires PARITY_BASELINE_ID. Does not touch Drupal.
 */
import { spawnSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');

if (!process.env.PARITY_BASELINE_ID) {
  console.error('Set PARITY_BASELINE_ID (e.g. df-impact-2026-10-28)');
  process.exit(1);
}

console.log(`Capturing WP baseline ${process.env.PARITY_BASELINE_ID}`);
console.log(`WP_BASE_URL=${process.env.WP_BASE_URL || 'https://danafarberimpact.org'}`);
console.log(`Sample mode: ${process.env.PARITY_SAMPLE !== '0'}`);

const pw = spawnSync(
  'npx',
  ['playwright', 'test', '--project=capture-baseline'],
  {
    cwd: parityRoot,
    stdio: 'inherit',
    env: { ...process.env },
  },
);

process.exit(pw.status || 0);
