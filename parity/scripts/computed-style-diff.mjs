#!/usr/bin/env node
/**
 * Live WordPress vs Drupal: computed styles + bounding boxes + light DOM structure.
 * Renderer-independent parity gate (fonts differ from Mac reference pack).
 *
 * Usage:
 *   DRUPAL_BASE_URL=http://127.0.0.1:8888 node scripts/computed-style-diff.mjs
 *   PARITY_TEMPLATE=issue node scripts/computed-style-diff.mjs
 *   PARITY_PATH=/fall-2026-issue PARITY_BP=desktop node scripts/computed-style-diff.mjs
 */
import { chromium } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { preparePage, dismissOverlays } from '../lib/page-utils.mjs';
import { stabilizeParityViewport } from '../lib/capture-stabilize.mjs';
import { startRefPackServer } from './lib/ref-pack-server.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const WP_SOURCE = (process.env.PARITY_WP_SOURCE || 'live').toLowerCase();
const wpBaseLive = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const packRoot = path.join(parityRoot, 'reference-pack');
let refPackServer = null;
let refPackBase = null;
const RECT_TOLERANCE = Number(process.env.PARITY_RECT_TOLERANCE_PX || 2);
const PARITY_GOTO_TIMEOUT_MS = Number(process.env.PARITY_GOTO_TIMEOUT_MS || 120000);
const PARITY_SETTLE_AFTER_LOAD_MS = Number(process.env.PARITY_SETTLE_AFTER_LOAD_MS || 800);
const PARITY_WP_MAX_ATTEMPTS = Number(process.env.PARITY_WP_MAX_ATTEMPTS || 4);
const PARITY_WP_RETRY_BASE_MS = Number(process.env.PARITY_WP_RETRY_BASE_MS || 2000);
const WP_SHELL_FOOTER_MAX_H = Number(process.env.PARITY_WP_SHELL_FOOTER_MAX_H || 80);
const CHROME_PROBE_SELECTORS = ['header', '.desktop-header', '.wp-block-main-header'];
const outDir = path.join(
  parityRoot,
  'reports',
  'computed-style-diff',
  process.env.REPORT_RUN_ID || String(Date.now()),
);

const viewports = {
  desktop: { width: 1440, height: 900 },
  tablet: { width: 768, height: 1024 },
  mobile: { width: 375, height: 812 },
};

const STYLE_KEYS = [
  'display',
  'position',
  'width',
  'height',
  'margin',
  'padding',
  'font-family',
  'font-size',
  'font-weight',
  'line-height',
  'letter-spacing',
  'text-transform',
  'color',
  'background-color',
  'gap',
  'max-width',
  'box-sizing',
];

const selectorConfig = JSON.parse(
  fs.readFileSync(path.join(parityRoot, 'config', 'parity-selectors.json'), 'utf8'),
);
const ALL_SELECTORS = [...new Set([...selectorConfig.chrome, ...selectorConfig.content])];

const samplesFile = process.env.PARITY_SAMPLES_JSON
  ? path.join(parityRoot, 'config', process.env.PARITY_SAMPLES_JSON)
  : path.join(parityRoot, 'config', 'template-samples.json');
const samples = JSON.parse(fs.readFileSync(samplesFile, 'utf8'));

function normalizeFontFamily(v) {
  return String(v)
    .split(',')[0]
    .replace(/['"]/g, '')
    .trim()
    .toLowerCase();
}

function normalizeStyle(key, v) {
  if (v === undefined || v === null) return '';
  const s = String(v).trim();
  if (key === 'font-family') return normalizeFontFamily(s);
  return s;
}

function pathSlug(p) {
  if (p === '/') return 'root';
  return p.replace(/^\/|\/$/g, '').replace(/\//g, '_') || 'root';
}

/** Live WP canonical path (legacy slugs that 404 or captcha more often). */
function wpParityPath(path) {
  if (path === '/digital-exclusives') {
    return '/category/digital-exclusives';
  }
  return path;
}

/** WP paths whose reference-pack folder differs from pathToPackSlug. */
const REF_PACK_SLUG_OVERRIDES = {
  '/digital-exclusives': 'category_digital-exclusives',
};

function pathToPackSlug(p) {
  const normalized = p.replace(/\/+$/, '') || '/';
  if (REF_PACK_SLUG_OVERRIDES[normalized]) {
    return REF_PACK_SLUG_OVERRIDES[normalized];
  }
  if (p === '/' || p === '') return 'root';
  if (p === '/homepage') return 'homepage';
  const m = p.match(/^\/(\d{4})\/(\d{2})\/(.+?)\/?$/);
  if (m) return `${m[1]}_${m[2]}_${m[3]}`;
  return p.replace(/^\/|\/$/g, '').replace(/\//g, '_');
}

async function ensureRefPackServer() {
  if (refPackBase) {
    return refPackBase;
  }
  const started = startRefPackServer(packRoot, { drupalBase });
  refPackServer = started.server;
  refPackBase = started.baseUrl;
  return refPackBase;
}

function jobsFromEnv() {
  const filterTemplate = process.env.PARITY_TEMPLATE;
  const filterPath = process.env.PARITY_PATH;
  const filterBp = process.env.PARITY_BP;
  const jobs = [];
  for (const [template, entry] of Object.entries(samples)) {
    if (filterTemplate && template !== filterTemplate) continue;
    for (const p of entry.paths) {
      if (filterPath && p !== filterPath) continue;
      for (const bp of Object.keys(viewports)) {
        if (filterBp && bp !== filterBp) continue;
        jobs.push({ template, path: p, bp });
      }
    }
  }
  return jobs;
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function probeWpPageShell(page) {
  const html = await page.content();
  const captchaHint = /sgcaptcha|siteground|captcha/i.test(html) && html.length < 35000;
  let hasChrome = false;
  for (const sel of CHROME_PROBE_SELECTORS) {
    if (await page.locator(sel).count()) {
      hasChrome = true;
      break;
    }
  }
  let footerH = 0;
  const footer = page.locator('footer').first();
  if (await footer.count()) {
    footerH = await footer.evaluate((el) => el.getBoundingClientRect().height);
  }
  const stubFooter = footerH > 0 && footerH < WP_SHELL_FOOTER_MAX_H;
  return { captchaHint, hasChrome, stubFooter, htmlLength: html.length };
}

/**
 * @returns {Promise<{status:number,captcha:boolean,shellReason:string|null,attempts:number,comparable:boolean}>}
 */
async function gotoWordPressParityPage(page, url) {
  let last = { status: 0, captcha: true, shellReason: 'navigation_failed', attempts: 0, comparable: false };
  for (let attempt = 1; attempt <= PARITY_WP_MAX_ATTEMPTS; attempt++) {
    const resp = await page.goto(url, { waitUntil: 'load', timeout: PARITY_GOTO_TIMEOUT_MS }).catch(() => null);
    await page.waitForTimeout(PARITY_SETTLE_AFTER_LOAD_MS);
    await dismissOverlays(page);
    await stabilizeParityViewport(page);
    const status = resp?.status() ?? 0;
    const shell = await probeWpPageShell(page);
    let shellReason = null;
    if (status === 202) shellReason = 'http_202';
    else if (shell.captchaHint) shellReason = 'captcha_html';
    else if (!shell.hasChrome) shellReason = 'missing_header_nav';
    else if (shell.stubFooter) shellReason = 'stub_footer';
    const comparable = status === 200 && !shellReason;
    last = {
      status,
      captcha: Boolean(shellReason),
      shellReason,
      attempts: attempt,
      comparable,
    };
    if (comparable) return last;
    if (attempt < PARITY_WP_MAX_ATTEMPTS) {
      await sleep(PARITY_WP_RETRY_BASE_MS * 2 ** (attempt - 1));
    }
  }
  return last;
}

async function gotoParityPage(page, url) {
  const resp = await page.goto(url, { waitUntil: 'load', timeout: PARITY_GOTO_TIMEOUT_MS }).catch(() => null);
  await page.waitForTimeout(PARITY_SETTLE_AFTER_LOAD_MS);
  await dismissOverlays(page);
  await stabilizeParityViewport(page);
  return { status: resp?.status() ?? 0, captcha: false };
}

async function measure(page, selector) {
  const el = page.locator(selector).first();
  if (!(await el.count())) return null;
  return el.evaluate(
    (node, keys) => {
      const cs = getComputedStyle(node);
      const r = node.getBoundingClientRect();
      const styles = {};
      for (const k of keys) styles[k] = cs.getPropertyValue(k);
      const img = node.tagName === 'IMG' ? node : node.querySelector('img');
      let imageMeta = null;
      if (img) {
        const ir = img.getBoundingClientRect();
        imageMeta = {
          naturalWidth: img.naturalWidth,
          naturalHeight: img.naturalHeight,
          renderedW: ir.width,
          renderedH: ir.height,
          aspectNatural: img.naturalHeight ? img.naturalWidth / img.naturalHeight : 0,
          aspectRendered: ir.height ? ir.width / ir.height : 0,
          src: (img.currentSrc || img.src || '').slice(0, 200),
        };
      }
      const domSummary = {
        tag: node.tagName.toLowerCase(),
        childElementCount: node.childElementCount,
        className: typeof node.className === 'string' ? node.className.slice(0, 120) : '',
      };
      return {
        rect: { x: r.x, y: r.y, w: r.width, h: r.height },
        styles,
        imageMeta,
        domSummary,
      };
    },
    STYLE_KEYS,
  );
}

function compareMeasurements(wp, dr, selector) {
  const mismatches = [];
  const area = Math.max((wp?.rect?.w ?? 0) * (wp?.rect?.h ?? 0), (dr?.rect?.w ?? 0) * (dr?.rect?.h ?? 0));

  if (!wp && !dr) return { mismatches, area: 0 };
  if (!wp || !dr) {
    mismatches.push({
      selector,
      kind: 'missing',
      wp: !!wp,
      drupal: !!dr,
      area: area || 99999,
    });
    return { mismatches, area: area || 99999 };
  }

  for (const dim of ['x', 'y', 'w', 'h']) {
    const delta = Math.abs(wp.rect[dim] - dr.rect[dim]);
    if (delta > RECT_TOLERANCE) {
      mismatches.push({
        selector,
        kind: 'rect',
        dim,
        wp: wp.rect[dim],
        drupal: dr.rect[dim],
        delta,
        area,
      });
    }
  }

  for (const key of STYLE_KEYS) {
    const a = normalizeStyle(key, wp.styles[key]);
    const b = normalizeStyle(key, dr.styles[key]);
    if (a !== b) {
      mismatches.push({
        selector,
        kind: 'style',
        key,
        wp: a,
        drupal: b,
        area,
      });
    }
  }

  if (wp.domSummary.tag !== dr.domSummary.tag) {
    mismatches.push({
      selector,
      kind: 'dom_tag',
      wp: wp.domSummary.tag,
      drupal: dr.domSummary.tag,
      area,
    });
  } else if (Math.abs(wp.domSummary.childElementCount - dr.domSummary.childElementCount) > 2) {
    mismatches.push({
      selector,
      kind: 'dom_children',
      wp: wp.domSummary.childElementCount,
      drupal: dr.domSummary.childElementCount,
      area,
    });
  }

  if (wp.imageMeta || dr.imageMeta) {
    const wn = wp.imageMeta?.naturalWidth ?? 0;
    const dn = dr.imageMeta?.naturalWidth ?? 0;
    if (wn !== dn && (wn || dn)) {
      mismatches.push({
        selector,
        kind: 'imgNatural',
        wp: wp.imageMeta,
        drupal: dr.imageMeta,
        area,
      });
    }
    const wr = wp.imageMeta?.renderedW ?? 0;
    const drw = dr.imageMeta?.renderedW ?? 0;
    if (wr > 10 && drw > 10 && Math.abs(wr - drw) > RECT_TOLERANCE) {
      mismatches.push({
        selector,
        kind: 'imgRendered',
        dim: 'w',
        wp: wr,
        drupal: drw,
        delta: Math.abs(wr - drw),
        area,
      });
    }
  }

  return { mismatches, area };
}

async function comparePath(browser, job) {
  const vp = viewports[job.bp] || viewports.desktop;
  const wpPage = await browser.newPage();
  const drPage = await browser.newPage();
  await preparePage(wpPage);
  await preparePage(drPage);
  await wpPage.setViewportSize(vp);
  await drPage.setViewportSize(vp);

  const drUrl = `${drupalBase}${job.path === '/' ? '' : job.path}`;

  let wpUrl;
  let wpNav;
  if (WP_SOURCE === 'refpack') {
    const base = await ensureRefPackServer();
    const slug = pathToPackSlug(job.path);
    wpUrl = `${base}/rendered/${slug}/${job.bp}/`;
    const resp = await wpPage
      .goto(wpUrl, { waitUntil: 'domcontentloaded', timeout: PARITY_GOTO_TIMEOUT_MS })
      .catch(() => null);
    await wpPage.waitForTimeout(PARITY_SETTLE_AFTER_LOAD_MS);
    await dismissOverlays(wpPage);
    await stabilizeParityViewport(wpPage);
    const nav = { status: resp?.status() ?? 0, captcha: false };
    wpNav = {
      status: nav.status,
      captcha: false,
      shellReason: nav.status === 200 ? null : 'refpack_missing',
      attempts: 1,
      comparable: nav.status === 200,
    };
  } else {
    const wpPath = wpParityPath(job.path);
    wpUrl = `${wpBaseLive}${wpPath === '/' ? '' : wpPath}`;
    wpNav = await gotoWordPressParityPage(wpPage, wpUrl);
  }
  const drNav = await gotoParityPage(drPage, drUrl);

  const perSelector = {};
  const mismatches = [];

  for (const selector of ALL_SELECTORS) {
    const wp = await measure(wpPage, selector);
    const dr = await measure(drPage, selector);
    perSelector[selector] = { wp, drupal: dr };
    const { mismatches: m } = compareMeasurements(wp, dr, selector);
    mismatches.push(...m);
  }

  mismatches.sort((a, b) => (b.area ?? 0) - (a.area ?? 0));

  await wpPage.close();
  await drPage.close();

  return {
    template: job.template,
    path: job.path,
    bp: job.bp,
    wpUrl,
    drUrl,
    wpStatus: wpNav.status,
    drStatus: drNav.status,
    wpCaptcha: wpNav.captcha,
    wpShellReason: wpNav.shellReason,
    wpAttempts: wpNav.attempts,
    wpComparable: wpNav.comparable,
    mismatchCount: mismatches.length,
    mismatches,
    topBoxDeltas: mismatches
      .filter((m) => m.kind === 'rect')
      .slice(0, 12)
      .map((m) => ({ selector: m.selector, dim: m.dim, delta: m.delta, wp: m.wp, drupal: m.drupal })),
    perSelector,
  };
}

const jobs = jobsFromEnv();
if (!jobs.length) {
  console.error('No jobs (check PARITY_TEMPLATE / PARITY_PATH / PARITY_BP)');
  process.exit(1);
}

fs.mkdirSync(outDir, { recursive: true });
const serial = process.env.PARITY_SERIAL === '1';
const interJobMs = Number(process.env.PARITY_INTER_JOB_MS || 1500);
const results = [];

async function runOneJob(browser, job) {
  const label = `${job.template}_${pathSlug(job.path)}_${job.bp}`;
  process.stderr.write(`compare ${label}\n`);
  try {
    const report = await comparePath(browser, job);
    if (report.wpStatus === 0 || report.wpStatus >= 400) {
      report.wpComparable = false;
    }
    if (report.drStatus !== 200) {
      throw new Error(`Drupal HTTP ${report.drStatus}`);
    }
    results.push(report);
    const outFile = path.join(outDir, `${label}.json`);
    fs.writeFileSync(outFile, JSON.stringify(report, null, 2));
    process.stderr.write(
      `  mismatches=${report.mismatchCount} wp=${report.wpStatus} comparable=${report.wpComparable} dr=${report.drStatus}\n`,
    );
  } catch (e) {
    results.push({ ...job, error: String(e), wpComparable: false });
    process.stderr.write(`  ERROR ${String(e)}\n`);
  }
}

if (serial) {
  for (const job of jobs) {
    const browser = await chromium.launch();
    await runOneJob(browser, job);
    await browser.close();
    if (interJobMs > 0) {
      await new Promise((r) => setTimeout(r, interJobMs));
    }
  }
} else {
  const browser = await chromium.launch();
  for (const job of jobs) {
    await runOneJob(browser, job);
  }
  await browser.close();
}

function median(nums) {
  const sorted = [...nums].sort((a, b) => a - b);
  if (!sorted.length) return null;
  const mid = Math.floor(sorted.length / 2);
  return sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
}

const byTemplate = {};
for (const r of results) {
  if (!r.template) continue;
  if (!byTemplate[r.template]) {
    byTemplate[r.template] = {
      runs: 0,
      mismatchesAll: 0,
      comparableRuns: 0,
      mismatchesComparable: 0,
      comparableMismatchCounts: [],
    };
  }
  const bucket = byTemplate[r.template];
  bucket.runs += 1;
  bucket.mismatchesAll += r.mismatchCount ?? 0;
  if (r.wpComparable) {
    bucket.comparableRuns += 1;
    bucket.mismatchesComparable += r.mismatchCount ?? 0;
    bucket.comparableMismatchCounts.push(r.mismatchCount ?? 0);
  }
}
for (const bucket of Object.values(byTemplate)) {
  bucket.medianMismatchComparable =
    median(bucket.comparableMismatchCounts) ?? null;
  delete bucket.comparableMismatchCounts;
}

if (refPackServer) {
  refPackServer.close();
}

const summary = {
  generatedAt: new Date().toISOString(),
  wpSource: WP_SOURCE,
  wpBase: WP_SOURCE === 'refpack' ? refPackBase : wpBaseLive,
  drupalBase,
  jobCount: jobs.length,
  byTemplate,
  results: results.map((r) => ({
    template: r.template,
    path: r.path,
    bp: r.bp,
    mismatchCount: r.mismatchCount,
    wpStatus: r.wpStatus,
    drStatus: r.drStatus,
    wpComparable: r.wpComparable,
    wpShellReason: r.wpShellReason,
    wpAttempts: r.wpAttempts,
    topBoxDeltas: r.topBoxDeltas,
    error: r.error,
  })),
};

fs.writeFileSync(path.join(outDir, 'summary.json'), JSON.stringify(summary, null, 2));
console.log(JSON.stringify(summary, null, 2));
