#!/usr/bin/env node
/**
 * Audit internal <a href> on Drupal parity URLs (~801 inventory paths).
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const wpHost = 'danafarberimpact.org';
const concurrency = Math.max(1, parseInt(process.env.AUDIT_CONCURRENCY || '6', 10));
const batchSize = Math.max(10, parseInt(process.env.AUDIT_BATCH_SIZE || '25', 10));
const maxLinksPerPage = parseInt(process.env.AUDIT_MAX_LINKS || '40', 10);

function loadInventoryPaths() {
  if (process.env.AUDIT_PATHS) {
    return process.env.AUDIT_PATHS.split(',').map((p) => p.trim()).filter(Boolean);
  }
  let invFile = process.env.URL_PARITY_REPORT;
  if (!invFile) {
    const dir = path.join(parityRoot, 'reports', 'url-parity');
    const candidates = fs
      .readdirSync(dir)
      .filter((f) => f.startsWith('check-') && f.endsWith('.json'))
      .map((f) => ({ f, m: fs.statSync(path.join(dir, f)).mtimeMs }))
      .sort((a, b) => b.m - a.m);
    invFile = candidates.length ? path.join(dir, candidates[0].f) : '';
  }
  if (!invFile || !fs.existsSync(invFile)) {
    throw new Error(`URL parity report missing (set URL_PARITY_REPORT or run parity:url:check)`);
  }
  const inv = JSON.parse(fs.readFileSync(invFile, 'utf8'));
  return inv.results.filter((r) => r.drupal?.ok).map((r) => r.path);
}

async function fetchHtml(url) {
  const res = await fetch(url, { redirect: 'follow' });
  return { status: res.status, html: res.ok ? await res.text() : '' };
}

function extractHrefs(html, pageUrl) {
  const hrefs = [];
  const re = /<a\b[^>]*\shref=["']([^"'#]+(?:#[^"']*)?)["']/gi;
  let m;
  while ((m = re.exec(html))) {
    let href = m[1].trim();
    if (!href || href.startsWith('mailto:') || href.startsWith('tel:') || href.startsWith('javascript:')) {
      continue;
    }
    if (href.startsWith('//')) href = `https:${href}`;
    else if (href.startsWith('/') || href.startsWith('?')) href = new URL(href, pageUrl).href;
    hrefs.push(href.split('#')[0]);
  }
  return hrefs;
}

function classifyHref(href, drupalOrigin) {
  try {
    const u = new URL(href);
    if (u.hostname === wpHost) {
      return { kind: 'wp_internal', href };
    }
    if (u.origin === drupalOrigin) {
      return { kind: 'drupal_internal', href: u.pathname + u.search };
    }
    return { kind: 'external', href };
  } catch {
    return { kind: 'invalid', href };
  }
}

async function headOk(url) {
  try {
    const res = await fetch(url, { method: 'HEAD', redirect: 'follow' });
    if (res.status >= 200 && res.status < 400) return res.status;
    const get = await fetch(url, { method: 'GET', redirect: 'follow' });
    return get.status;
  } catch {
    return 0;
  }
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
const drupalOrigin = new URL(drupalBase).origin;
console.error(`link-audit: ${paths.length} paths`);

/** @type {Map<string, {path: string, href: string, issue: string, status: number|null}>} */
const issues = new Map();
const checked = new Set();
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
        return;
      }
      const hrefs = [...new Set(extractHrefs(html, pageUrl))].slice(0, maxLinksPerPage);
      for (const href of hrefs) {
        const key = href;
        if (checked.has(key) || issues.has(key)) continue;
        checked.add(key);
        const cls = classifyHref(href, drupalOrigin);
        if (cls.kind === 'external') continue;
        if (cls.kind === 'wp_internal') {
          issues.set(key, { path: p, href, issue: 'wp_internal_href', status: null });
          continue;
        }
        if (cls.kind === 'invalid') {
          issues.set(key, { path: p, href, issue: 'invalid_href', status: null });
          continue;
        }
        const target = `${drupalBase}${cls.href.startsWith('/') ? cls.href : `/${cls.href}`}`;
        const st = await headOk(target);
        if (st < 200 || st >= 400) {
          issues.set(key, { path: p, href: cls.href, issue: `http_${st || 'fail'}`, status: st || null });
        }
      }
    },
    concurrency,
  );
}

const broken = [...issues.values()];
const outDir = path.join(parityRoot, 'reports', 'drupal-link-audit');
fs.mkdirSync(outDir, { recursive: true });
const stamp = new Date().toISOString().replace(/[:.]/g, '-');
const reportPath = path.join(outDir, `audit-${stamp}.json`);
const summary = {
  generatedAt: new Date().toISOString(),
  drupalBase,
  inventoryPaths: paths.length,
  pagesHttpFailed: pagesFailed,
  uniqueHrefsChecked: checked.size,
  issueCount: broken.length,
  wpInternalCount: broken.filter((b) => b.issue === 'wp_internal_href').length,
  httpFailCount: broken.filter((b) => b.issue.startsWith('http_')).length,
  issues: broken,
};
fs.writeFileSync(reportPath, JSON.stringify(summary, null, 2));
fs.writeFileSync(path.join(outDir, 'latest-summary.json'), JSON.stringify(summary, null, 2));

console.log(
  JSON.stringify(
    {
      reportPath,
      inventoryPaths: paths.length,
      uniqueHrefsChecked: checked.size,
      issueCount: broken.length,
      wpInternalCount: summary.wpInternalCount,
      httpFailCount: summary.httpFailCount,
    },
    null,
    2,
  ),
);
