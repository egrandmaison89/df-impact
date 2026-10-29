#!/usr/bin/env node
/**
 * Audit <img src> on Drupal template sample paths; compare HTTP status vs WP.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const wpBase = 'https://danafarberimpact.org';

const samples = JSON.parse(
  fs.readFileSync(path.join(parityRoot, 'config', 'template-samples.json'), 'utf8'),
);
const paths = [...new Set(Object.values(samples).flatMap((e) => e.paths || []))];

async function fetchHtml(url) {
  const res = await fetch(url, { redirect: 'follow' });
  return { status: res.status, html: res.ok ? await res.text() : '' };
}

async function headOk(url) {
  try {
    const res = await fetch(url, { method: 'HEAD', redirect: 'follow' });
    return res.status >= 200 && res.status < 400;
  } catch {
    return false;
  }
}

function extractImgSrcs(html, pageUrl) {
  const srcs = [];
  const re = /<img\b[^>]*\ssrc=["']([^"']+)["']/gi;
  let m;
  while ((m = re.exec(html))) {
    let src = m[1];
    if (src.startsWith('//')) src = `https:${src}`;
    else if (src.startsWith('/')) src = new URL(src, pageUrl).href;
    srcs.push(src);
  }
  return srcs;
}

const broken = [];
const checked = new Set();

for (const p of paths) {
  const pageUrl = `${drupalBase}${p.startsWith('/') ? p : `/${p}`}`;
  const { status, html } = await fetchHtml(pageUrl);
  if (status >= 400) {
    broken.push({ path: p, src: pageUrl, issue: `page_http_${status}`, wpOk: null });
    continue;
  }
  for (const src of extractImgSrcs(html, pageUrl)) {
    if (checked.has(src)) continue;
    checked.add(src);
    const ok = await headOk(src);
    if (ok) continue;
    let wpOk = null;
    if (src.includes('/sites/default/files/') || src.includes('wp-images')) {
      const rel = src.split('/sites/default/files/').pop();
      const wpUrl = `${wpBase}/wp-content/uploads/${rel.replace(/^wp-images\//, '')}`;
      wpOk = await headOk(wpUrl);
    }
    broken.push({ path: p, src, issue: 'img_http_fail', wpOk });
  }
}

const outDir = path.join(parityRoot, 'reports', 'drupal-image-audit');
fs.mkdirSync(outDir, { recursive: true });
const stamp = new Date().toISOString().replace(/[:.]/g, '-');
const reportPath = path.join(outDir, `audit-${stamp}.json`);
fs.writeFileSync(
  reportPath,
  JSON.stringify(
    {
      drupalBase,
      paths: paths.length,
      imagesChecked: checked.size,
      brokenCount: broken.length,
      broken,
    },
    null,
    2,
  ),
);
console.log(JSON.stringify({ reportPath, brokenCount: broken.length, imagesChecked: checked.size }, null, 2));
