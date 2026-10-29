#!/usr/bin/env node
/**
 * Manifest URL parity: same path returns 200 on Drupal without redirect (unless WP baseline redirects).
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { isContentParityUrl, publicPath } from '../lib/manifest-urls.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const manifest = JSON.parse(fs.readFileSync(path.join(parityRoot, 'manifest/site-manifest.json'), 'utf8'));
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const wpBase = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');
const limit = parseInt(process.env.URL_PARITY_LIMIT || '0', 10);
const concurrency = parseInt(process.env.URL_PARITY_CONCURRENCY || '24', 10);
const drupalOnly = process.env.URL_PARITY_DRUPAL_ONLY === '1';

let urls = (manifest.urls || []).filter(isContentParityUrl);
if (limit > 0) urls = urls.slice(0, limit);

async function checkEntry(entry) {
  const p = publicPath(entry);
  const drUrl = `${drupalBase}${p}`;
  const wpUrl = entry.url || `${wpBase}${p}`;
  let dr = { status: null, location: null, ok: false };
  let wp = { status: null, location: null, ok: false, skipped: drupalOnly };
  try {
    const r = await fetch(drUrl, { redirect: 'manual' });
    dr.status = r.status;
    dr.location = r.headers.get('location');
    dr.ok = r.status === 200 && !dr.location;
  } catch (e) {
    dr.error = String(e);
  }
  if (!drupalOnly && !dr.ok) {
    try {
      const r = await fetch(wpUrl, { redirect: 'manual' });
      wp.status = r.status;
      wp.location = r.headers.get('location');
      wp.ok = r.status === 200 && !wp.location;
      wp.skipped = false;
    } catch (e) {
      wp.error = String(e);
      wp.skipped = false;
    }
  }
  const ok =
    dr.ok ||
    (!drupalOnly &&
      wp.status >= 300 &&
      wp.status < 400 &&
      dr.status === wp.status);
  return { path: p, drupal: dr, wordpress: wp, ok };
}

const results = [];
for (let i = 0; i < urls.length; i += concurrency) {
  const batch = urls.slice(i, i + concurrency);
  const batchResults = await Promise.all(batch.map((entry) => checkEntry(entry)));
  results.push(...batchResults);
}
const pass = results.filter((r) => r.ok).length;

const report = {
  generatedAt: new Date().toISOString(),
  drupalBase,
  checked: urls.length,
  urlParityPercent: urls.length ? (pass / urls.length) * 100 : 0,
  pass,
  results,
};

const outDir = path.join(parityRoot, 'reports', 'url-parity');
fs.mkdirSync(outDir, { recursive: true });
const outFile = path.join(outDir, `check-${Date.now()}.json`);
fs.writeFileSync(outFile, JSON.stringify(report, null, 2));
console.log(JSON.stringify({ outFile, checked: report.checked, urlParityPercent: report.urlParityPercent, pass: report.pass }, null, 2));
process.exit(pass === urls.length ? 0 : 1);
