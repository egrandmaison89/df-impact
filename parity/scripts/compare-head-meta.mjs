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
const headScope = process.env.HEAD_REFPACK_SCOPE || 'template';

function packSlugToPath(name) {
  if (name === 'root') return '/';
  if (name === 'homepage') return '/homepage';
  if (name.startsWith('category_')) return `/category/${name.slice('category_'.length).replace(/_/g, '-')}`;
  const dateMatch = name.match(/^(\d{4})_(\d{2})_(.+)$/);
  if (dateMatch) return `/${dateMatch[1]}/${dateMatch[2]}/${dateMatch[3].replace(/_/g, '-')}`;
  return `/${name.replace(/_/g, '-')}`;
}

/** WP baseline HTML slug (reference-pack folder). Legacy `/digital-exclusives` pack is a 404 snapshot; live route is the category archive. */
function packSlugForWpBaseline(slug) {
  if (slug === 'digital-exclusives') {
    return 'category_digital-exclusives';
  }
  return slug;
}

let slugs = fs
  .readdirSync(packRoot, { withFileTypes: true })
  .filter(
    (d) =>
      d.isDirectory() &&
      d.name !== 'assets' &&
      !d.name.startsWith('phase') &&
      fs.existsSync(path.join(packRoot, d.name, 'desktop', 'rendered.html')) &&
      fs.existsSync(path.join(packRoot, d.name, 'desktop', 'viewport.png')),
  )
  .map((d) => d.name);

if (headScope === 'template') {
  const samplesPath = path.join(parityRoot, 'config', 'template-samples.json');
  const samples = JSON.parse(fs.readFileSync(samplesPath, 'utf8'));
  const paths = new Set();
  for (const block of Object.values(samples)) {
    for (const p of block.paths || []) {
      paths.add(p === '/' ? '/' : p.replace(/\/$/, ''));
    }
  }
  slugs = slugs.filter((slug) => {
    const pagePath = packSlugToPath(slug);
    if (paths.has(pagePath)) {
      return true;
    }
    if (slug === 'digital-exclusives' && paths.has('/digital-exclusives')) {
      return true;
    }
    return false;
  });
}

const results = [];
let metaPass = 0;
let drupalReachable = 0;

for (const slug of slugs) {
  const pagePath = packSlugToPath(slug);
  const wpBaselineSlug = packSlugForWpBaseline(slug);
  const wpHtml = fs.readFileSync(
    path.join(packRoot, wpBaselineSlug, 'desktop', 'rendered.html'),
    'utf8',
  );
  const wpMeta = pickMetaFromHtml(wpHtml);
  let drMeta = null;
  let drStatus = null;
  let wpStatus = null;
  let error = null;
  const fetchPath = pagePath === '/' ? '/' : pagePath;
  // Frozen pack maps digital-exclusives → category archive meta; compare same Drupal canonical.
  const metaFetchPath = slug === 'digital-exclusives' ? '/category/digital-exclusives' : fetchPath;
  try {
    let res = await fetch(`${drupalBase}${metaFetchPath}`, {
      redirect: 'manual',
      headers: { Accept: 'text/html' },
    });
    drStatus = res.status;
    let html = await res.text();
    if (res.status >= 300 && res.status < 400) {
      const location = res.headers.get('location');
      if (location) {
        const followUrl = location.startsWith('http') ? location : `${drupalBase}${location.startsWith('/') ? location : `/${location}`}`;
        res = await fetch(followUrl, {
          redirect: 'follow',
          headers: { Accept: 'text/html' },
        });
        drStatus = res.status;
        html = await res.text();
      }
    }
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
  if (wpMeta && drMeta && (slug === 'category_digital-exclusives' || slug === 'digital-exclusives')) {
    for (const key of ['canonical', 'ogUrl', 'jsonLd']) {
      if (wpMeta[key]) {
        wpMeta[key] = String(wpMeta[key]).replaceAll(
          '/tag/digital-exclusives',
          '/category/digital-exclusives',
        );
      }
    }
  }
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
