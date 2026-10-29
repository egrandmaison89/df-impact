#!/usr/bin/env node
/**
 * Live WP vs Drupal hover computed-style parity (Eric requirement).
 * Targets: nav, cards, buttons, footer links — per template sample paths (desktop).
 */
import { chromium } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { preparePage, dismissOverlays } from '../lib/page-utils.mjs';
import { stabilizeParityViewport } from '../lib/capture-stabilize.mjs';
import { computedStyle, computedStyleHover, scrollSelectorIntoView } from '../lib/behavior-utils.mjs';
import { WP_PROFILE, DRUPAL_PROFILE } from '../lib/behavior-profiles.mjs';
import { compareHoverStyles, styleFieldMatch } from '../lib/behavior-compare.mjs';
import { startRefPackServer } from './lib/ref-pack-server.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const WP_SOURCE = (process.env.PARITY_WP_SOURCE || 'live').toLowerCase();
const wpBaseLive = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const packRoot = path.join(parityRoot, 'reference-pack');
let refPackServer = null;
let refPackBase = null;
const runId = process.env.REPORT_RUN_ID || `hover-cs-${Date.now()}`;
const outDir = path.join(parityRoot, 'reports', 'computed-style-hover', runId);
const vp = { width: 1440, height: 900 };

const samples = JSON.parse(
  fs.readFileSync(path.join(parityRoot, 'config', 'template-samples.json'), 'utf8'),
);

const HOVER_TARGETS = [
  { key: 'navLink', label: 'Nav link' },
  { key: 'card', label: 'Card' },
  { key: 'button', label: 'Button' },
  { key: 'footerLink', label: 'Footer link' },
];

function wpParityPath(p) {
  if (p === '/digital-exclusives') return '/category/digital-exclusives';
  return p;
}

const REF_PACK_SLUG_OVERRIDES = { '/digital-exclusives': 'category_digital-exclusives' };

function pathToPackSlug(p) {
  const normalized = p.replace(/\/+$/, '') || '/';
  if (REF_PACK_SLUG_OVERRIDES[normalized]) return REF_PACK_SLUG_OVERRIDES[normalized];
  if (p === '/' || p === '') return 'root';
  if (p === '/homepage') return 'homepage';
  const m = p.match(/^\/(\d{4})\/(\d{2})\/(.+?)\/?$/);
  if (m) return `${m[1]}_${m[2]}_${m[3]}`;
  return p.replace(/^\/|\/$/g, '').replace(/\//g, '_');
}

async function ensureRefPackServer() {
  if (refPackBase) return refPackBase;
  const started = startRefPackServer(packRoot, { drupalBase });
  refPackServer = started.server;
  refPackBase = started.baseUrl;
  return refPackBase;
}

function pathSlug(p) {
  if (p === '/') return 'root';
  return p.replace(/^\/|\/$/g, '').replace(/\//g, '_') || 'root';
}

async function gotoWp(page, url) {
  const maxAttempts = Number(process.env.PARITY_WP_MAX_ATTEMPTS || 4);
  const baseMs = Number(process.env.PARITY_WP_RETRY_BASE_MS || 2000);
  let status = 0;
  for (let attempt = 1; attempt <= maxAttempts; attempt++) {
    const resp = await page.goto(url, { waitUntil: 'load', timeout: 120000 }).catch(() => null);
    await page.waitForTimeout(800);
    await dismissOverlays(page);
    await stabilizeParityViewport(page);
    status = resp?.status() ?? 0;
    const html = await page.content();
    const captcha = /sgcaptcha|siteground|captcha/i.test(html) && html.length < 35000;
    const hasHeader = (await page.locator('header, .desktop-header').count()) > 0;
    if (status === 200 && !captcha && hasHeader) return status;
    if (attempt < maxAttempts) await new Promise((r) => setTimeout(r, baseMs * 2 ** (attempt - 1)));
  }
  return status;
}

async function gotoDr(page, url) {
  const resp = await page.goto(url, { waitUntil: 'load', timeout: 120000 }).catch(() => null);
  await page.waitForTimeout(800);
  await dismissOverlays(page);
  await stabilizeParityViewport(page);
  return resp?.status() ?? 0;
}

async function captureHoverPair(page, profile, targetKey) {
  const sel = profile.selectors[targetKey];
  if (!sel) return null;
  if (targetKey === 'footerLink' || targetKey === 'card' || targetKey === 'button') {
    await scrollSelectorIntoView(page, sel);
  }
  const def = await computedStyle(page, sel);
  const hov = await computedStyleHover(page, sel);
  return { default: def, hover: hov, selector: sel };
}

async function compareJob(browser, template, p) {
  const wpPage = await browser.newPage();
  const drPage = await browser.newPage();
  await preparePage(wpPage);
  await preparePage(drPage);
  await wpPage.setViewportSize(vp);
  await drPage.setViewportSize(vp);

  const drUrl = `${drupalBase}${p === '/' ? '' : p}`;
  let wpUrl;
  let wpStatus;
  if (WP_SOURCE === 'refpack') {
    const base = await ensureRefPackServer();
    wpUrl = `${base}/rendered/${pathToPackSlug(p)}/desktop/`;
    const resp = await wpPage.goto(wpUrl, { waitUntil: 'domcontentloaded', timeout: 120000 }).catch(() => null);
    await wpPage.waitForTimeout(800);
    await dismissOverlays(wpPage);
    await stabilizeParityViewport(wpPage);
    wpStatus = resp?.status() ?? 0;
  } else {
    const wpPath = wpParityPath(p);
    wpUrl = `${wpBaseLive}${wpPath === '/' ? '' : wpPath}`;
    wpStatus = await gotoWp(wpPage, wpUrl);
  }
  const drStatus = await gotoDr(drPage, drUrl);

  const targetResults = [];
  let mismatchCount = 0;

  for (const { key, label } of HOVER_TARGETS) {
    const wpCap = await captureHoverPair(wpPage, WP_PROFILE, key);
    const drCap = await captureHoverPair(drPage, DRUPAL_PROFILE, key);
    const cmp = compareHoverStyles(wpCap?.default, wpCap?.hover, drCap?.default, drCap?.hover);
    if (!cmp.ok) mismatchCount += cmp.diffs.length;
    targetResults.push({
      target: key,
      label,
      ok: cmp.ok,
      diffs: cmp.diffs,
      wp: { default: wpCap?.default, hover: wpCap?.hover },
      drupal: { default: drCap?.default, hover: drCap?.hover },
    });
  }

  await wpPage.close();
  await drPage.close();

  return {
    template,
    path: p,
    wpUrl,
    drUrl,
    wpStatus,
    drStatus,
    mismatchCount,
    targets: targetResults,
  };
}

function jobsFromEnv() {
  const filterTemplate = process.env.PARITY_TEMPLATE;
  const filterPath = process.env.PARITY_PATH;
  const jobs = [];
  for (const [template, entry] of Object.entries(samples)) {
    if (filterTemplate && template !== filterTemplate) continue;
    for (const p of entry.paths) {
      if (filterPath && p !== filterPath) continue;
      jobs.push({ template, path: p });
    }
  }
  return jobs;
}

const jobs = jobsFromEnv();
if (!jobs.length) {
  console.error('No hover jobs');
  process.exit(1);
}

fs.mkdirSync(outDir, { recursive: true });

const browser = await chromium.launch({ headless: true });
const results = [];
for (const job of jobs) {
  process.stderr.write(`hover ${job.template} ${job.path}\n`);
  try {
    const report = await compareJob(browser, job.template, job.path);
    const file = path.join(outDir, `${job.template}_${pathSlug(job.path)}_desktop.json`);
    fs.writeFileSync(file, JSON.stringify(report, null, 2));
    results.push({
      template: job.template,
      path: job.path,
      mismatchCount: report.mismatchCount,
      wpStatus: report.wpStatus,
      drStatus: report.drStatus,
    });
  } catch (e) {
    process.stderr.write(String(e).slice(-200) + '\n');
  }
  await new Promise((r) => setTimeout(r, 1200));
}
await browser.close();
if (refPackServer) {
  refPackServer.close();
}

const byTemplate = {};
for (const r of results) {
  if (!byTemplate[r.template]) byTemplate[r.template] = { sum: 0, paths: 0 };
  byTemplate[r.template].sum += r.mismatchCount;
  byTemplate[r.template].paths += 1;
}

const aggregate = {
  generatedAt: new Date().toISOString(),
  runId,
  jobCount: results.length,
  mismatchSumByTemplate: byTemplate,
  results,
};
fs.writeFileSync(path.join(outDir, 'aggregate.json'), JSON.stringify(aggregate, null, 2));
console.log(JSON.stringify(aggregate, null, 2));
