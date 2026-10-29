#!/usr/bin/env node
/**
 * Offline WP reference-pack head vs live Drupal (metaMatch fields + extended head).
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { pickMetaFromHtml } from '../lib/head-meta.mjs';
import { metaMatches } from '../lib/thresholds.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const packRoot = path.join(parityRoot, 'reference-pack');
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const wpBase = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');
const checkLiveWp404 = process.env.HEAD_REFPACK_CHECK_WP !== '0';

function packSlugToPath(name) {
  if (name === 'root') return '/';
  if (name === 'homepage') return '/homepage';
  if (name.startsWith('category_')) return `/category/${name.slice('category_'.length).replace(/_/g, '-')}`;
  const dateMatch = name.match(/^(\d{4})_(\d{2})_(.+)$/);
  if (dateMatch) return `/${dateMatch[1]}/${dateMatch[2]}/${dateMatch[3].replace(/_/g, '-')}`;
  return `/${name.replace(/_/g, '-')}`;
}

const slugs = fs
  .readdirSync(packRoot, { withFileTypes: true })
  .filter(
    (d) =>
      d.isDirectory() &&
      d.name !== 'assets' &&
      !d.name.startsWith('phase') &&
      fs.existsSync(path.join(packRoot, d.name, 'desktop', 'rendered.html')),
  )
  .map((d) => d.name);

const results = [];
let metaPass = 0;
let drupalReachable = 0;

for (const slug of slugs) {
  const pagePath = packSlugToPath(slug);
  const wpHtml = fs.readFileSync(path.join(packRoot, slug, 'desktop', 'rendered.html'), 'utf8');
  const wpMeta = pickMetaFromHtml(wpHtml);
  let drMeta = null;
  let drStatus = null;
  let wpStatus = null;
  let error = null;
  const fetchPath = pagePath === '/' ? '/' : pagePath;
  try {
    const res = await fetch(`${drupalBase}${fetchPath}`, {
      redirect: 'manual',
      headers: { Accept: 'text/html' },
    });
    drStatus = res.status;
    const html = await res.text();
    if (res.status >= 200 && res.status < 400) {
      drupalReachable += 1;
      drMeta = pickMetaFromHtml(html);
    }
    else if (res.status === 404) {
      drMeta = pickMetaFromHtml(html);
    }
  } catch (e) {
    error = String(e);
  }
  if (checkLiveWp404 && drStatus === 404) {
    try {
      const wpRes = await fetch(`${wpBase}${fetchPath}`, {
        redirect: 'manual',
        signal: AbortSignal.timeout(20000),
      });
      wpStatus = wpRes.status;
    } catch (e) {
      wpStatus = null;
    }
  }
  const dual404 = drStatus === 404 && wpStatus === 404;
  const match = dual404 || (wpMeta && drMeta ? metaMatches(wpMeta, drMeta) : false);
  if (match) metaPass += 1;
  results.push({
    slug,
    path: pagePath,
    wpMeta,
    drMeta,
    drStatus,
    wpStatus,
    dual404,
    metaMatch: match,
    error,
  });
}

const report = {
  generatedAt: new Date().toISOString(),
  drupalBase,
  pageCount: slugs.length,
  drupalReachable,
  metaMatchPercent: slugs.length ? (metaPass / slugs.length) * 100 : 0,
  metaPass,
  results,
};

const outDir = path.join(parityRoot, 'reports', 'head-meta');
fs.mkdirSync(outDir, { recursive: true });
const outFile = path.join(outDir, `compare-${Date.now()}.json`);
fs.writeFileSync(outFile, JSON.stringify(report, null, 2));
console.log(JSON.stringify({ outFile, ...report, results: undefined }, null, 2));

process.exit(drupalReachable === 0 ? 2 : metaPass === slugs.length ? 0 : 1);
