#!/usr/bin/env node
/**
 * Compare Drupal head/meta to expected values from WXR-derived JSON + ref-pack + overrides.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { isContentParityUrl, publicPath } from '../lib/manifest-urls.mjs';
import { pickMetaFromHtml } from '../lib/head-meta.mjs';
import { metaMatches } from '../lib/thresholds.mjs';
import { buildExpectedMetaIndex, expectedMetaForPath } from '../lib/wp-expected-meta.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const manifest = JSON.parse(
  fs.readFileSync(path.join(parityRoot, 'manifest', 'site-manifest.json'), 'utf8'),
);
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const limit = parseInt(process.env.HEAD_MANIFEST_LIMIT || '0', 10);
const concurrency = parseInt(process.env.HEAD_MANIFEST_CONCURRENCY || '8', 10);

const index = buildExpectedMetaIndex();
let urls = (manifest.urls || []).filter(isContentParityUrl);
if (limit > 0) urls = urls.slice(0, limit);

const results = [];
let checked = 0;
let withExpected = 0;
let drupalOk = 0;
let metaPass = 0;

async function fetchDrupalMeta(pagePath) {
  const res = await fetch(`${drupalBase}${pagePath}`, {
    redirect: 'manual',
    headers: { Accept: 'text/html' },
  });
  if (res.status !== 200) return { status: res.status, meta: null };
  const html = await res.text();
  return { status: res.status, meta: pickMetaFromHtml(html) };
}

async function worker(batch) {
  for (const entry of batch) {
    const p = publicPath(entry);
    const expected = expectedMetaForPath(index, p);
    checked += 1;
    if (!expected) {
      results.push({ path: p, skipped: true, reason: 'no_expected_source' });
      continue;
    }
    withExpected += 1;
    let dr;
    try {
      dr = await fetchDrupalMeta(p);
    } catch (e) {
      results.push({ path: p, expected, error: String(e), metaMatch: false });
      continue;
    }
    if (dr.status === 200 && dr.meta) drupalOk += 1;
    const match = dr.meta && metaMatches(expected, dr.meta);
    if (match) metaPass += 1;
    results.push({
      path: p,
      drupalStatus: dr.status,
      expected,
      drMeta: dr.meta,
      metaMatch: match,
    });
  }
}

const chunks = [];
for (let i = 0; i < urls.length; i += concurrency) {
  chunks.push(urls.slice(i, i + concurrency));
}
for (const chunk of chunks) {
  await worker(chunk);
}

const report = {
  generatedAt: new Date().toISOString(),
  method:
    'articles.json + in_brief.json + wp_head_overrides.json + reference-pack HTML (see parity/lib/wp-expected-meta.mjs)',
  drupalBase,
  manifestUrls: urls.length,
  checked,
  withExpected,
  drupalOk,
  metaPass,
  metaMatchPercent: withExpected ? (metaPass / withExpected) * 100 : 0,
  indexSize: index.size,
  results,
};

const outDir = path.join(parityRoot, 'reports', 'head-meta');
fs.mkdirSync(outDir, { recursive: true });
const outFile = path.join(outDir, `manifest-${Date.now()}.json`);
fs.writeFileSync(outFile, JSON.stringify(report, null, 2));
console.log(
  JSON.stringify(
    {
      outFile,
      manifestUrls: report.manifestUrls,
      withExpected: report.withExpected,
      drupalOk: report.drupalOk,
      metaPass: report.metaPass,
      metaMatchPercent: report.metaMatchPercent,
      indexSize: report.indexSize,
    },
    null,
    2,
  ),
);

process.exit(withExpected > 0 && metaPass === withExpected && drupalOk === withExpected ? 0 : 1);
