#!/usr/bin/env node
/**
 * Full-site content parity sweep (801 manifest URLs).
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { isContentParityUrl, publicPath } from '../lib/manifest-urls.mjs';
import {
  baselinePathForFrozen,
  frozenWpHtmlPath,
  normalizePath,
} from '../lib/content-sweep-path.mjs';
import {
  extractContentMetrics,
  isChallengeHtml,
  listsEqual,
  wordJaccardSimilarity,
} from '../lib/content-sweep-extract.mjs';
import { httpGet } from '../lib/http-get.mjs';
import { wpArchiveExportHtmlForPath, wpExportHtmlForPath } from '../lib/wp-export-baseline.mjs';
import { loadIssueTagArchiveFallbackHtml, loadIssueWpHtml } from '../lib/issue-wp-baseline.mjs';
import { textSimilarity } from '../lib/page-utils.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const packRoot = path.join(parityRoot, 'reference-pack');
const manifest = JSON.parse(
  fs.readFileSync(path.join(parityRoot, 'manifest/site-manifest.json'), 'utf8'),
);
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(
  /\/$/,
  '',
);
const wpBase = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');
const concurrency = Number(process.env.CONTENT_SWEEP_CONCURRENCY || 6);
const maxAttempts = Number(process.env.CONTENT_SWEEP_RETRIES || 4);
const retryDelayMs = Number(process.env.CONTENT_SWEEP_RETRY_MS || 2000);
const linkCheck = process.env.CONTENT_SWEEP_SKIP_LINK_CHECK !== '1';
const maxLinksPerPage = Number(process.env.CONTENT_SWEEP_MAX_LINKS || 12);
const rowTimeoutMs = Number(process.env.CONTENT_SWEEP_ROW_TIMEOUT_MS || 90000);

function withTimeout(promise, ms, label) {
  return Promise.race([
    promise,
    new Promise((_, reject) => {
      setTimeout(() => reject(new Error(`row timeout ${label} after ${ms}ms`)), ms);
    }),
  ]);
}
const limit = Number(process.env.CONTENT_SWEEP_LIMIT || 0);
const pathFilterRaw = (process.env.CONTENT_SWEEP_PATHS || '').trim();
const pathFilter = pathFilterRaw
  ? pathFilterRaw
      .split(',')
      .map((p) => normalizePath(p.trim()))
      .filter(Boolean)
  : [];

const urls = (manifest.urls || []).filter(isContentParityUrl);
let selected = urls;
if (pathFilter.length) {
  selected = urls.filter((entry) => pathFilter.includes(normalizePath(publicPath(entry))));
}
const entries = (limit > 0 ? selected.slice(0, limit) : selected).map((entry) => ({
  entry,
  path: publicPath(entry),
}));

async function fetchHtml(url, label) {
  let lastErr = null;
  const timeoutMs = label === 'wp' ? 28000 : 45000;
  const attempts = label === 'wp' ? Math.min(maxAttempts, 3) : maxAttempts;
  for (let attempt = 1; attempt <= attempts; attempt++) {
    try {
      const r = await httpGet(url, { timeoutMs });
      return { html: r.body, status: r.status, url, attempt };
    } catch (e) {
      lastErr = e;
      if (attempt < maxAttempts) {
        await new Promise((res) => setTimeout(res, retryDelayMs * attempt));
      }
    }
  }
  return { error: String(lastErr), attempt: maxAttempts };
}

const linkStatusCache = new Map();

async function drupalLinkOk(href) {
  let target = href;
  if (href.startsWith('http')) {
    try {
      const u = new URL(href);
      if (!u.hostname.includes('danafarberimpact.org') && !u.hostname.includes('127.0.0.1')) {
        return { skipped: true, external: true };
      }
      target = u.pathname + u.search;
    } catch {
      return { ok: false, error: 'bad-url' };
    }
  }
  target = normalizePath(target.split('?')[0]);
  if (linkStatusCache.has(target)) return linkStatusCache.get(target);
  const url = `${drupalBase}${target}`;
  try {
    const r = await httpGet(url, { timeoutMs: 20000 });
    const ok = r.status >= 200 && r.status < 400;
    const out = { ok, status: r.status, path: target };
    linkStatusCache.set(target, out);
    return out;
  } catch (e) {
    const out = { ok: false, error: String(e), path: target };
    linkStatusCache.set(target, out);
    return out;
  }
}

function clusterRow(row) {
  const c = [];
  if (row.wpComparable === false) c.push('wp_non_comparable');
  if (row.drupalStatus !== 200) c.push('drupal_http');
  if (row.textSimilarity != null && row.textSimilarity < 0.98) c.push('low_text_similarity');
  if (row.unrenderedShortcodes > 0) c.push('unrendered_shortcodes');
  if (row.wpInternalLinksInDrupal > 0) c.push('wp_internal_links_in_drupal');
  if (row.brokenInternalLinks?.length) c.push('broken_internal_links');
  if (row.featuredImageMatch === false) c.push('featured_image_mismatch');
  if (row.h1Match === false || row.titleMatch === false) c.push('heading_title_mismatch');
  if (row.publishDateMatch === false || row.authorMatch === false) c.push('byline_date_mismatch');
  if (row.categoryMatch === false) c.push('category_mismatch');
  if (Math.abs((row.inlineImageDelta ?? 0)) > 1) c.push('inline_image_count');
  if (Math.abs((row.embedDelta ?? 0)) > 0) c.push('embed_count');
  return c.length ? c : ['pass'];
}

async function processOne({ entry, path: p }) {
  try {
  const row = {
    path: p,
    template: entry.template || 'other',
    wpSource: null,
    wpComparable: true,
    wpStatus: null,
    drupalStatus: null,
    textSimilarity: null,
    pass: false,
  };

  let wpHtml = null;
  const frozen = frozenWpHtmlPath(packRoot, p);
  const isIssue = row.template === 'issue';
  const exportHtml = isIssue ? null : wpExportHtmlForPath(p);
  const archiveExportHtml = !exportHtml ? wpArchiveExportHtmlForPath(p) : null;
  const issueCacheHtml = isIssue ? loadIssueWpHtml(p) : null;
  const issueTagFallbackHtml = isIssue && !issueCacheHtml ? loadIssueTagArchiveFallbackHtml(p) : null;
  if (frozen) {
    wpHtml = fs.readFileSync(frozen, 'utf8');
    row.wpSource = 'frozen';
    if (baselinePathForFrozen(p) !== normalizePath(p)) {
      row.wpSource = 'frozen-paginated';
    }
    row.wpStatus = 200;
  } else if (issueCacheHtml) {
    wpHtml = issueCacheHtml;
    row.wpSource = 'issue-cache';
    row.wpStatus = 200;
  } else if (issueTagFallbackHtml) {
    wpHtml = issueTagFallbackHtml;
    row.wpSource = 'issue-tag-frozen';
    row.wpStatus = 200;
  } else if (exportHtml) {
    wpHtml = exportHtml;
    row.wpSource = 'export';
    row.wpStatus = 200;
  } else if (archiveExportHtml) {
    wpHtml = archiveExportHtml;
    row.wpSource = 'export-archive';
    row.wpStatus = 200;
  } else if (isIssue) {
    row.wpComparable = false;
    row.wpError = 'no_issue_wp_baseline';
    row.clusters = ['wp_non_comparable'];
    return row;
  } else {
    const wpUrl = entry.url || `${wpBase}${p}`;
    const wpRes = await fetchHtml(wpUrl, 'wp');
    if (wpRes.error) {
      row.wpComparable = false;
      row.wpError = wpRes.error;
      row.clusters = ['wp_fetch_failed'];
      return row;
    }
    wpHtml = wpRes.html;
    row.wpStatus = wpRes.status;
    row.wpSource = 'live';
    if (isChallengeHtml(wpHtml, wpUrl)) {
      const fallbackArchive = wpArchiveExportHtmlForPath(p);
      if (fallbackArchive) {
        wpHtml = fallbackArchive;
        row.wpSource = 'export-archive';
      } else {
        row.wpComparable = false;
        row.clusters = ['wp_non_comparable'];
        return row;
      }
    }
  }

  const dr = await fetchHtml(`${drupalBase}${p}`, 'drupal');
  if (dr.error) {
    row.drupalError = dr.error;
    row.clusters = ['drupal_fetch_failed'];
    return row;
  }
  row.drupalStatus = dr.status;
  const drHtml = dr.html;
  if (dr.status !== 200) {
    row.clusters = ['drupal_http'];
    return row;
  }

  const wpM = extractContentMetrics(wpHtml);
  const drM = extractContentMetrics(drHtml);
  const useTextSimilarity = isIssue || row.template === 'archive';
  row.textSimilarity = useTextSimilarity
    ? textSimilarity(wpM.text, drM.text)
    : wordJaccardSimilarity(wpM.text, drM.text);
  row.h1Match = !wpM.h1 || wpM.h1 === drM.h1 || drM.h1.includes(wpM.h1) || wpM.h1.includes(drM.h1);
  row.titleMatch =
    !wpM.title ||
    wpM.title === drM.title ||
    drM.title.includes(wpM.title) ||
    wpM.title.includes(drM.title);
  row.publishDateMatch = !wpM.publishDate || wpM.publishDate === drM.publishDate;
  row.authorMatch = !wpM.author || wpM.author === drM.author;
  row.categoryMatch = listsEqual(wpM.categories, drM.categories);
  row.featuredImageMatch =
    !wpM.featuredBasename || wpM.featuredBasename === drM.featuredBasename;
  row.inlineImageDelta = drM.inlineImages - wpM.inlineImages;
  row.embedDelta = drM.embeds - wpM.embeds;
  row.unrenderedShortcodes = drM.unrenderedShortcodes;
  row.shortcodeSamples = drM.shortcodeSamples;
  row.wpInternalLinksInDrupal = drM.wpInternalLinks.length;
  row.wpInternalLinkSamples = drM.wpInternalLinks.slice(0, 5);

  row.brokenInternalLinks = [];
  const linkLimit =
    row.template === 'archive' || String(p).includes('/page/')
      ? Math.min(maxLinksPerPage, 6)
      : maxLinksPerPage;
  if (linkCheck && row.template !== 'issue') {
    for (const href of drM.links.slice(0, linkLimit)) {
      if (href.startsWith('http') && !href.includes('danafarberimpact.org') && !href.includes('127.0.0.1')) {
        continue;
      }
      if (href.startsWith('mailto:') || href.startsWith('tel:')) continue;
      const chk = await drupalLinkOk(href);
      if (chk.skipped) continue;
      if (!chk.ok) {
        row.brokenInternalLinks.push({ href, status: chk.status, error: chk.error });
      }
    }
  }

  row.pass =
    row.wpComparable &&
    row.textSimilarity >= 0.98 &&
    row.unrenderedShortcodes === 0 &&
    row.wpInternalLinksInDrupal === 0 &&
    row.brokenInternalLinks.length === 0;

  row.clusters = clusterRow(row);
  return row;
  } catch (e) {
    return {
      path: p,
      template: entry.template || 'other',
      error: String(e),
      clusters: ['sweep_error'],
      wpComparable: false,
    };
  }
}

const ts = Date.now();
const outDir = path.join(parityRoot, 'reports', 'content-sweep');
fs.mkdirSync(outDir, { recursive: true });
const outJson = path.join(outDir, `sweep-${ts}.json`);
const outCsv = path.join(outDir, `sweep-${ts}.csv`);

const rows = [];
for (let i = 0; i < entries.length; i += concurrency) {
  const batch = entries.slice(i, i + concurrency);
  const batchRows = await Promise.allSettled(
    batch.map((entry) => {
      const tpl = entry.entry.template || 'other';
      const ms =
        tpl === 'archive' || tpl === 'issue' || tpl === 'search' ? 150000 : rowTimeoutMs;
      return withTimeout(processOne(entry), ms, entry.path);
    }),
  );
  rows.push(
    ...batchRows.map((r, idx) =>
      r.status === 'fulfilled'
        ? r.value
        : {
            path: batch[idx].path,
            error: String(r.reason),
            clusters: ['sweep_error'],
            wpComparable: false,
          },
    ),
  );
  process.stderr.write(`\rcontent-sweep ${Math.min(i + concurrency, entries.length)}/${entries.length}`);
}
process.stderr.write('\n');

const comparable = rows.filter((r) => r.wpComparable !== false && r.drupalStatus === 200);
const passed = comparable.filter((r) => r.pass);
const report = {
  generatedAt: new Date().toISOString(),
  drupalBase,
  wpBase,
  total: rows.length,
  wpNonComparable: rows.filter((r) => r.wpComparable === false).length,
  comparable: comparable.length,
  passed: passed.length,
  passRateAmongComparable: comparable.length ? passed.length / comparable.length : 0,
  text98AmongComparable: comparable.filter((r) => (r.textSimilarity ?? 0) >= 0.98).length,
  text98AmongAll: rows.filter((r) => (r.textSimilarity ?? 0) >= 0.98).length,
  text98RateAmongAll: rows.length
    ? rows.filter((r) => (r.textSimilarity ?? 0) >= 0.98).length / rows.length
    : 0,
  rows,
};

fs.writeFileSync(outJson, JSON.stringify(report, null, 2));

const csvHeader =
  'path,template,wpSource,wpComparable,textSimilarity,pass,unrenderedShortcodes,wpInternalLinksInDrupal,brokenLinks,clusters';
const csvLines = rows.map((r) => {
  const esc = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
  return [
    esc(r.path),
    esc(r.template),
    esc(r.wpSource),
    esc(r.wpComparable),
    esc(r.textSimilarity?.toFixed(4) ?? ''),
    esc(r.pass),
    esc(r.unrenderedShortcodes ?? 0),
    esc(r.wpInternalLinksInDrupal ?? 0),
    esc(r.brokenInternalLinks?.length ?? 0),
    esc((r.clusters || []).join('|')),
  ].join(',');
});
fs.writeFileSync(outCsv, [csvHeader, ...csvLines].join('\n'));

console.log(
  JSON.stringify(
    {
      outJson,
      outCsv,
      total: report.total,
      comparable: report.comparable,
      passed: report.passed,
      passRateAmongComparable: report.passRateAmongComparable,
      text98AmongComparable: report.text98AmongComparable,
    },
    null,
    2,
  ),
);

const inScopeRows = rows.filter((r) => r.template !== 'issue');
const text98InScope = inScopeRows.filter((r) => (r.textSimilarity ?? 0) >= 0.98).length;
report.text98InScopeExcludingIssues = text98InScope;
report.text98RateInScopeExcludingIssues = inScopeRows.length
  ? text98InScope / inScopeRows.length
  : 0;

if (
  report.text98RateInScopeExcludingIssues < 0.98 &&
  report.text98RateAmongAll < 0.98 &&
  report.passRateAmongComparable < 0.98
) {
  process.exitCode = 1;
}
