#!/usr/bin/env node
/**
 * Pre-push / CI guard: every template-sample path + manifest sample inventory
 * must return Drupal HTTP < 500 with no PHP fatal in body or new watchdog php errors.
 *
 * Usage:
 *   DRUPAL_BASE_URL=http://127.0.0.1:8888 node parity/scripts/smoke-200.mjs
 *   SMOKE_DRUPAL_REQUIRED=1 npm run parity:smoke:200
 */
import fs from 'node:fs';
import path from 'node:path';
import { execSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { loadUrls, normalizePath, publicPath } from '../lib/manifest-urls.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const repoRoot = path.resolve(parityRoot, '..');
const drupalWeb = path.join(repoRoot, 'drupal/web');
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const concurrency = Number(process.env.SMOKE_CONCURRENCY || 10);
const timeoutMs = Number(process.env.SMOKE_TIMEOUT_MS || 25000);
const strict = process.env.SMOKE_DRUPAL_REQUIRED !== '0';

const FATAL_BODY_RE =
  /(Fatal error|Parse error|Uncaught (?:Error|Exception)|The website encountered an unexpected error)/i;

function templateSamplePaths() {
  const cfg = JSON.parse(
    fs.readFileSync(path.join(parityRoot, 'config', 'template-samples.json'), 'utf8'),
  );
  const out = new Set();
  for (const group of Object.values(cfg)) {
    for (const p of group.paths || []) {
      out.add(normalizePath(p));
    }
  }
  return out;
}

function inventorySamplePaths() {
  const prevSample = process.env.PARITY_SAMPLE;
  const prevLimit = process.env.PARITY_LIMIT;
  process.env.PARITY_SAMPLE = '1';
  delete process.env.PARITY_LIMIT;
  const cwd = process.cwd();
  process.chdir(parityRoot);
  let urls;
  try {
    urls = loadUrls().map((entry) => publicPath(entry));
  } finally {
    process.chdir(cwd);
    if (prevSample === undefined) delete process.env.PARITY_SAMPLE;
    else process.env.PARITY_SAMPLE = prevSample;
    if (prevLimit === undefined) delete process.env.PARITY_LIMIT;
    else process.env.PARITY_LIMIT = prevLimit;
  }
  return urls.map((p) => normalizePath(p));
}

function collectPaths() {
  const merged = [...templateSamplePaths(), ...inventorySamplePaths()];
  const seen = new Set();
  return merged.filter((p) => {
    if (seen.has(p)) return false;
    seen.add(p);
    return true;
  });
}

function maxWatchdogWidBefore() {
  if (!fs.existsSync(path.join(drupalWeb, '../vendor/bin/drush'))) {
    return 0;
  }
  try {
    const out = execSync('../vendor/bin/drush sql:query "SELECT COALESCE(MAX(wid),0) FROM watchdog"', {
      cwd: drupalWeb,
      encoding: 'utf8',
      stdio: ['ignore', 'pipe', 'pipe'],
    });
    return Number(String(out).trim()) || 0;
  } catch {
    return 0;
  }
}

function fetchPhpWatchdogSince(widAfter) {
  if (!fs.existsSync(path.join(drupalWeb, '../vendor/bin/drush'))) {
    return [];
  }
  try {
    const out = execSync(
      `../vendor/bin/drush sql:query "SELECT wid, severity, message FROM watchdog WHERE type='php' AND wid > ${widAfter} ORDER BY wid DESC LIMIT 15"`,
      { cwd: drupalWeb, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] },
    );
    return String(out)
      .trim()
      .split('\n')
      .filter((line) => line && !line.startsWith('wid'));
  } catch {
    return [];
  }
}

async function fetchPath(p) {
  const url = `${drupalBase}${p === '/' ? '/' : p}`;
  try {
    const res = await fetch(url, {
      redirect: 'manual',
      headers: { Accept: 'text/html' },
      signal: AbortSignal.timeout(timeoutMs),
    });
    const body = await res.text();
    const fatalInBody = FATAL_BODY_RE.test(body);
    return {
      path: p,
      status: res.status,
      ok: res.status < 500 && !fatalInBody,
      fatalInBody,
      location: res.headers.get('location'),
    };
  } catch (e) {
    return { path: p, status: null, ok: false, error: String(e) };
  }
}

async function runPool(paths, worker) {
  const results = [];
  let i = 0;
  async function w() {
    while (i < paths.length) {
      const idx = i++;
      results[idx] = await worker(paths[idx]);
    }
  }
  await Promise.all(Array.from({ length: Math.min(concurrency, paths.length) }, () => w()));
  return results;
}

const paths = collectPaths();
const widBefore = maxWatchdogWidBefore();

let probe;
try {
  probe = await fetch(`${drupalBase}/`, { signal: AbortSignal.timeout(5000) });
} catch (e) {
  const msg = `Drupal unreachable at ${drupalBase}: ${e}`;
  if (strict) {
    console.error(msg);
    process.exit(2);
  }
  console.warn(msg);
  process.exit(0);
}

if (!probe.ok && probe.status >= 500) {
  console.error(`Drupal homepage returned ${probe.status} before smoke run`);
  process.exit(1);
}

const results = await runPool(paths, fetchPath);
await new Promise((r) => setTimeout(r, 300));
const watchdogLines = fetchPhpWatchdogSince(widBefore);

const httpFails = results.filter((r) => !r.ok || (r.status !== null && r.status >= 500));
const statusFails = results.filter((r) => r.status !== null && r.status >= 500);

const report = {
  generatedAt: new Date().toISOString(),
  drupalBase,
  pathCount: paths.length,
  httpFailCount: httpFails.length,
  status5xxCount: statusFails.length,
  watchdogPhpNew: watchdogLines.length,
  fails: httpFails.map((r) => ({
    path: r.path,
    status: r.status,
    fatalInBody: r.fatalInBody,
    error: r.error,
    location: r.location,
  })),
  watchdog: watchdogLines,
};

const outDir = path.join(parityRoot, 'reports', 'smoke-200');
fs.mkdirSync(outDir, { recursive: true });
const outFile = path.join(outDir, `smoke-${Date.now()}.json`);
fs.writeFileSync(outFile, JSON.stringify(report, null, 2));

console.log(JSON.stringify({ outFile, ...report, watchdog: report.watchdog.slice(0, 3) }, null, 2));

const failed = httpFails.length > 0 || watchdogLines.length > 0;
process.exit(failed ? 1 : 0);
