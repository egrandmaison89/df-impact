#!/usr/bin/env node
/**
 * Gate: every path in template-samples.json must return HTTP 2xx from Drupal.
 * Run before push: npm run parity:template-samples:http
 */
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');

const samples = JSON.parse(
  fs.readFileSync(path.join(parityRoot, 'config', 'template-samples.json'), 'utf8'),
);
const paths = [...new Set(Object.values(samples).flatMap((e) => e.paths || []))];

function httpStatus(url) {
  const r = spawnSync(
    'curl',
    ['-sS', '-o', '/dev/null', '-w', '%{http_code}', '-L', '--max-time', '30', url],
    { encoding: 'utf8' },
  );
  if (r.error || r.status !== 0) {
    return { status: 0, error: r.error?.message || r.stderr || 'curl failed' };
  }
  return { status: parseInt(String(r.stdout).trim(), 10) || 0 };
}

const failures = [];
for (const p of paths) {
  const url = `${drupalBase}${p.startsWith('/') ? p : `/${p}`}`;
  const { status, error } = httpStatus(url);
  if (status < 200 || status >= 400) {
    failures.push({ path: p, status, url, error });
  }
}

const summary = {
  drupalBase,
  checked: paths.length,
  ok: paths.length - failures.length,
  failures,
};

console.log(JSON.stringify(summary, null, 2));
process.exit(failures.length === 0 ? 0 : 1);
