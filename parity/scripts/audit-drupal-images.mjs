#!/usr/bin/env node
/**
 * Audit <img src> on Drupal pages; default inventory = all URL-parity paths (~801).
 * Local files are verified on disk (HEAD on 127.0.0.1 often fails for PHP-built URLs).
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const repoRoot = path.resolve(parityRoot, '..');
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const wpBase = 'https://danafarberimpact.org';
const filesRoot = path.join(repoRoot, 'drupal', 'web', 'sites', 'default', 'files');
const concurrency = Math.max(1, parseInt(process.env.AUDIT_CONCURRENCY || '3', 10));
const batchSize = Math.max(10, parseInt(process.env.AUDIT_BATCH_SIZE || '20', 10));

function loadInventoryPaths() {
  if (process.env.AUDIT_PATHS) {
    return process.env.AUDIT_PATHS.split(',').map((p) => p.trim()).filter(Boolean);
  }
  if (process.env.AUDIT_SAMPLES === '1') {
    const samples = JSON.parse(
      fs.readFileSync(path.join(parityRoot, 'config', 'template-samples.json'), 'utf8'),
    );
    return [...new Set(Object.values(samples).flatMap((e) => e.paths || []))];
  }
  const invFile =
    process.env.URL_PARITY_REPORT ||
    path.join(parityRoot, 'reports', 'url-parity', 'check-1793239889622.json');
  const inv = JSON.parse(fs.readFileSync(invFile, 'utf8'));
  return inv.results.filter((r) => r.drupal?.ok).map((r) => r.path);
}

async function fetchHtml(url) {
  const res = await fetch(url, { redirect: 'follow' });
  return { status: res.status, html: res.ok ? await res.text() : '' };
}

function uriFromPublicUrl(src) {
  let normalized = src.replace(/^https?:\/\/[^/]+/i, '').replace(/^\/+/, '');
  normalized = normalized.replace(/&amp;/g, '&').split('?')[0];
  if (normalized.startsWith('sites/default/files/')) {
    return normalized.slice('sites/default/files/'.length);
  }
  return null;
}

/** Resolve image style URL to a source under wp-images/ or wp-content/uploads/. */
function resolveStyleSourceRel(rel) {
  const m = rel.match(/^styles\/[^/]+\/public\/(wp-images|wp-content\/uploads)\/(.+)$/);
  if (!m) return null;
  return `${m[1]}/${decodeURIComponent(m[2])}`;
}

function globSourceExists(relPath) {
  const dir = path.dirname(relPath);
  const stem = path.basename(relPath).toLowerCase();
  const baseDir = path.join(filesRoot, dir);
  if (!fs.existsSync(baseDir)) return false;
  const entries = fs.readdirSync(baseDir);
  const hit = entries.find((name) => name.toLowerCase() === stem || name.toLowerCase().startsWith(`${stem}.`));
  if (hit && fs.statSync(path.join(baseDir, hit)).size > 128) return true;
  const fuzzy = entries.find((name) => name.toLowerCase().replace(/[^a-z0-9]/g, '') === stem.replace(/[^a-z0-9]/g, ''));
  return Boolean(fuzzy && fs.statSync(path.join(baseDir, fuzzy)).size > 128);
}

function localFileOk(src) {
  let rel = uriFromPublicUrl(src);
  if (!rel) return null;
  const styled = resolveStyleSourceRel(rel);
  if (styled) {
    if (globSourceExists(styled)) return true;
    rel = styled;
  }
  const full = path.join(filesRoot, rel);
  try {
    const st = fs.statSync(full);
    return st.isFile() && st.size > 128;
  } catch {
    if (rel.startsWith('wp-images/')) {
      return globSourceExists(rel);
    }
    return false;
  }
}

async function remoteOk(url) {
  try {
    const res = await fetch(url, { method: 'GET', redirect: 'follow', headers: { Range: 'bytes=0-511' } });
    return res.status >= 200 && res.status < 400;
  } catch {
    return false;
  }
}

function wpCandidates(src) {
  const out = [];
  const rel = uriFromPublicUrl(src);
  if (rel) {
    if (rel.startsWith('wp-images/')) {
      const bare = rel.replace(/^wp-images\//, '');
      out.push(`${wpBase}/wp-content/uploads/${bare}`);
      out.push(`${wpBase}/wp-content/uploads/2023/01/${bare}`);
      const parts = bare.split('/');
      if (parts.length === 1) {
        out.push(`${wpBase}/wp-content/uploads/${bare}`);
      }
    } else if (rel.startsWith('wp-content/uploads/')) {
      out.push(`${wpBase}/${rel}`);
    }
  }
  if (src.includes('danafarberimpact.org/wp-content/uploads/')) {
    out.push(src.split('?')[0]);
  }
  if (src.includes('i0.wp.com/')) {
    out.push(src.split('?')[0]);
  }
  const uniq = [...new Set(out)];
  for (const u of [...uniq]) {
    if (!u.includes('danafarberimpact.org/wp-content/uploads/')) {
      continue;
    }
    const m = u.match(/wp-content\/uploads\/(.+)$/);
    if (m) {
      uniq.push(`https://i0.wp.com/danafarberimpact.org/wp-content/uploads/${m[1]}?ssl=1`);
    }
  }
  return [...new Set(uniq)];
}

async function imageReachable(src) {
  const local = localFileOk(src);
  if (local === true) return { ok: true, via: 'local' };
  if (local === false) {
    /* continue to HTTP */
  }
  if (src.startsWith('http://default/')) {
    const fixed = src.replace('http://default', drupalBase);
    const localFixed = localFileOk(fixed);
    if (localFixed === true) return { ok: true, via: 'local_default_host' };
    src = fixed;
  }
  const ok = await remoteOk(src);
  return { ok, via: ok ? 'http' : 'fail' };
}

function extractImgSrcs(html, pageUrl) {
  const srcs = [];
  const re = /<img\b[^>]*\ssrc=["']([^"']+)["']/gi;
  let m;
  while ((m = re.exec(html))) {
    let src = m[1];
    if (src.startsWith('data:')) continue;
    if (src.startsWith('//')) src = `https:${src}`;
    else if (src.startsWith('/')) src = new URL(src, pageUrl).href;
    srcs.push(src);
  }
  return srcs;
}

async function mapPool(items, fn, limit) {
  const results = [];
  let i = 0;
  async function worker() {
    while (i < items.length) {
      const idx = i++;
      results[idx] = await fn(items[idx], idx);
    }
  }
  await Promise.all(Array.from({ length: Math.min(limit, items.length) }, () => worker()));
  return results;
}

const paths = loadInventoryPaths();
console.error(`audit: ${paths.length} paths (AUDIT_PATHS=${process.env.AUDIT_PATHS || ''})`);
/** @type {Map<string, {path: string, src: string, issue: string, wpOk: boolean|null, wpBrokenToo: boolean|null}>} */
const brokenBySrc = new Map();
const checked = new Set();
const checkedOk = new Set();
let pagesFailed = 0;

for (let offset = 0; offset < paths.length; offset += batchSize) {
  const slice = paths.slice(offset, offset + batchSize);
  await mapPool(
    slice,
    async (p) => {
      const pageUrl = `${drupalBase}${p.startsWith('/') ? p : `/${p}`}`;
      const { status, html } = await fetchHtml(pageUrl);
      if (status >= 400) {
        pagesFailed++;
        brokenBySrc.set(pageUrl, {
          path: p,
          src: pageUrl,
          issue: `page_http_${status}`,
          wpOk: null,
          wpBrokenToo: null,
        });
        return;
      }
      const srcs = extractImgSrcs(html, pageUrl);
      for (const src of srcs) {
        if (checkedOk.has(src) || brokenBySrc.has(src)) continue;
        if (checked.has(src)) continue;
        checked.add(src);
        const reach = await imageReachable(src);
        if (reach.ok) {
          checkedOk.add(src);
          continue;
        }

        let wpOk = false;
        let wpBrokenToo = true;
        for (const candidate of wpCandidates(src)) {
          if (await remoteOk(candidate)) {
            wpOk = true;
            wpBrokenToo = false;
            break;
          }
        }
        brokenBySrc.set(src, {
          path: p,
          src,
          issue: reach.via === 'fail' ? 'img_unreachable' : 'img_http_fail',
          wpOk,
          wpBrokenToo,
        });
      }
    },
    concurrency,
  );
  if (offset % 100 === 0) {
    console.error(`audit progress: ${Math.min(offset + batchSize, paths.length)}/${paths.length} paths`);
  }
}

const broken = [...brokenBySrc.values()];

const wpBrokenCount = broken.filter((b) => b.wpBrokenToo === true).length;
const uniqueImagesChecked = checkedOk.size + checked.size;
const outDir = path.join(parityRoot, 'reports', 'drupal-image-audit');
fs.mkdirSync(outDir, { recursive: true });
const stamp = new Date().toISOString().replace(/[:.]/g, '-');
const reportPath = path.join(outDir, `audit-${stamp}.json`);
const summary = {
  generatedAt: new Date().toISOString(),
  drupalBase,
  inventoryPaths: paths.length,
  pagesHttpFailed: pagesFailed,
  uniqueImagesChecked,
  brokenCount: broken.length,
  brokenOnWpToo: wpBrokenCount,
  broken,
};
fs.writeFileSync(reportPath, JSON.stringify(summary, null, 2));
fs.writeFileSync(path.join(outDir, 'latest-summary.json'), JSON.stringify(summary, null, 2));

console.log(
  JSON.stringify(
    {
      reportPath,
      inventoryPaths: paths.length,
      uniqueImagesChecked,
      brokenCount: broken.length,
      brokenOnWpToo: wpBrokenCount,
    },
    null,
    2,
  ),
);
