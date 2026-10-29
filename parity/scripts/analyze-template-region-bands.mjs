#!/usr/bin/env node
/**
 * Semantic horizontal bands + cascade hint for Mac viewport diffs.
 *
 * Env: ANALYZE_PATH, ANALYZE_BP (desktop|tablet|mobile), ANALYZE_ID (qa folder name),
 *      DRUPAL_BASE_URL, optional ANALYZE_PROGRESS_DIR (existing compare output).
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';
import { PNG } from 'pngjs';
import pixelmatch from 'pixelmatch';
import { stabilizeParityViewport } from '../lib/capture-stabilize.mjs';
import { dismissOverlays, waitForFonts } from '../lib/page-utils.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const packRoot = path.join(parityRoot, 'reference-pack');
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://df-impact.ddev.site').replace(/\/$/, '');
const pagePath = process.env.ANALYZE_PATH || '/fall-2026-issue';
const bp = process.env.ANALYZE_BP || 'desktop';
const analyzeId = process.env.ANALYZE_ID || `region-${pagePath.replace(/\//g, '_')}-${bp}`;
const BPS = { desktop: 1440, tablet: 768, mobile: 375 };
const width = BPS[bp] || 1440;

const REGION_PROFILES = {
  issue: [
    { id: 'hero_banner', label: 'Hero / banner', selectors: ['.issue-page-banner', '.issue-page > .wp-block-cover'] },
    { id: 'intro', label: 'Intro / description', selectors: ['.issue-page__description'] },
    { id: 'in_this_issue_heading', label: 'In This Issue heading', selectors: ['#IN_THIS_ISSUE', 'h2.orange-border-header'] },
    { id: 'card_grid', label: 'PostX card grid', selectors: ['.ultp-block-70af6c', '.view-id-issue-articles', '.view-issue-articles'] },
    { id: 'in_brief_band', label: 'In Brief band', selectors: ['.wp-block-stackable-columns.stk-c3abd9d', '.in-brief-button'] },
    { id: 'promo_row', label: 'Promo row', selectors: ['.ultp-block-dd35a6', '.ultp-post-grid-block'] },
    { id: 'footer', label: 'Footer (in viewport)', selectors: ['footer'] },
  ],
  archive: [
    { id: 'archive_header', label: 'Archive header / title', selectors: ['main h1', '.wp-block-query-title'] },
    { id: 'archive_filters', label: 'Filters / intro', selectors: ['main .wp-block-query', '.archive-description'] },
    { id: 'card_rows', label: 'Card / post rows', selectors: ['main .ultp-block-item', 'main .wp-block-post', 'main .views-row'] },
    { id: 'pagination', label: 'Pagination', selectors: ['main .pager', 'main .wp-block-query-pagination'] },
    { id: 'footer', label: 'Footer', selectors: ['footer'] },
  ],
  landing: [
    { id: 'page_title', label: 'Page title band', selectors: ['main h1.wp-block-heading', 'main h1'] },
    { id: 'issue_covers', label: 'Issue cover grid', selectors: ['main figure.issue-cover', 'main .issue-cover', 'main .browse-issues'] },
    { id: 'browse_body', label: 'Browse body / views', selectors: ['main .view-browse-issues', 'main .view-content', 'main .entry-content'] },
    { id: 'footer', label: 'Footer', selectors: ['footer'] },
  ],
};

function pathToPackSlug(p) {
  if (p === '/homepage') return 'homepage';
  const m = p.match(/^\/(\d{4})\/(\d{2})\/(.+?)\/?$/);
  if (m) return `${m[1]}_${m[2]}_${m[3]}`;
  return p.replace(/^\/|\/$/g, '').replace(/\//g, '_');
}

function diffStats(wpData, drData, w, h, y0, y1) {
  let cnt = 0;
  let tot = 0;
  for (let y = y0; y < y1; y++) {
    for (let x = 0; x < w; x++) {
      const i = (w * y + x) * 4;
      tot++;
      if (wpData[i] !== drData[i] || wpData[i + 1] !== drData[i + 1] || wpData[i + 2] !== drData[i + 2]) {
        cnt++;
      }
    }
  }
  return tot ? cnt / tot : 0;
}

function bandScan(diffData, w, h, rows = 40) {
  const rh = Math.max(1, Math.floor(h / rows));
  const bands = [];
  for (let r = 0; r < rows; r++) {
    const y0 = r * rh;
    const y1 = Math.min((r + 1) * rh, h);
    let cnt = 0;
    let tot = 0;
    for (let y = y0; y < y1; y++) {
      for (let x = 0; x < w; x++) {
        const i = (w * y + x) * 4;
        tot++;
        if (diff.data?.[i]) cnt++;
      }
    }
    bands.push({ row: r, y0, y1, ratio: tot ? cnt / tot : 0 });
  }
  return bands;
}

function estimateVerticalShift(wp, dr, w, h, step = 4, maxShift = 120) {
  let best = { dy: 0, score: Infinity };
  for (let dy = -maxShift; dy <= maxShift; dy += step) {
    let sum = 0;
    let cnt = 0;
    for (let y = 0; y < h; y++) {
      const yb = y + dy;
      if (yb < 0 || yb >= h) continue;
      for (let x = 0; x < w; x++) {
        const ia = (w * y + x) * 4;
        const ib = (w * yb + x) * 4;
        sum +=
          Math.abs(wp.data[ia] - dr.data[ib]) +
          Math.abs(wp.data[ia + 1] - dr.data[ib + 1]) +
          Math.abs(wp.data[ia + 2] - dr.data[ib + 2]);
        cnt += 3;
      }
    }
    const score = cnt ? sum / cnt : Infinity;
    if (score < best.score) best = { dy, score };
  }
  return best;
}

function detectCascade(bands, overall, shift) {
  const baseline = shift.score;
  const shifted = shift.dy !== 0 ? shift.score : baseline;
  const improvement = baseline > 0 ? (baseline - shifted) / baseline : 0;
  if (Math.abs(shift.dy) >= 8 && improvement >= 0.12) {
    return {
      mode: 'vertical_offset_cascade',
      bestShiftPx: shift.dy,
      diffImprovementIfAligned: +(improvement * 100).toFixed(1),
      reason: `Aligning Drupal viewport by ${shift.dy}px reduces mean channel delta ~${(improvement * 100).toFixed(0)}% → fix first band height/offset (~y ${bands[0]?.y0 ?? 0}).`,
    };
  }
  const threshold = Math.max(0.2, overall * 0.5);
  const hot = bands.filter((b) => b.ratio >= threshold);
  return {
    mode: 'per_element',
    bestShiftPx: shift.dy,
    diffImprovementIfAligned: +(improvement * 100).toFixed(1),
    hotBandCount: hot.length,
    reason:
      hot.length >= 3
        ? 'Diffs stay high across many bands; vertical shift does not explain most pixels (content/style per region).'
        : 'Localized hot bands → per-element styling or content mismatch.',
  };
}

function profileKeyForPath(p) {
  if (p.includes('-issue')) return 'issue';
  if (p.startsWith('/category/') || p === '/digital-exclusives' || p === '/issues') return 'archive';
  return 'landing';
}

const packSlug = pathToPackSlug(pagePath);
const progressDir =
  process.env.ANALYZE_PROGRESS_DIR ||
  path.join(parityRoot, 'reports', 'progress', process.env.PROGRESS_COMPONENT || profileKeyForPath(pagePath), `${packSlug}_${bp}`);
const outDir = path.join(parityRoot, 'reports', 'progress', 'mac-visual', 'qa-evidence', analyzeId);
fs.mkdirSync(outDir, { recursive: true });

const wpPath = path.join(progressDir, 'wp-viewport-mac.png');
const drPath = path.join(progressDir, 'drupal-viewport.png');
const refVp = path.join(packRoot, packSlug, bp, 'viewport.png');

if (!fs.existsSync(wpPath) && fs.existsSync(refVp)) {
  fs.copyFileSync(refVp, wpPath);
}

const browser = await chromium.launch();
const page = await browser.newPage();
await page.setViewportSize({ width, height: 900 });
await page.goto(`${drupalBase}${pagePath}`, { waitUntil: 'load', timeout: 120000 });
await dismissOverlays(page);
await waitForFonts(page);
await stabilizeParityViewport(page);

const profileKey = profileKeyForPath(pagePath);
const regionsDef = REGION_PROFILES[profileKey] || REGION_PROFILES.issue;

const regionBounds = await page.evaluate((defs) => {
  const scrollY = window.scrollY;
  const docH = Math.max(document.documentElement.scrollHeight, document.body?.scrollHeight || 0);
  const out = [];
  for (const def of defs) {
    let top = Infinity;
    let bottom = 0;
    let found = false;
    for (const sel of def.selectors) {
      document.querySelectorAll(sel).forEach((el) => {
        const r = el.getBoundingClientRect();
        if (r.height < 2) return;
        found = true;
        top = Math.min(top, r.top + scrollY);
        bottom = Math.max(bottom, r.bottom + scrollY);
      });
    }
    if (found && bottom > top) {
      out.push({ id: def.id, label: def.label, y0: Math.max(0, Math.floor(top)), y1: Math.ceil(bottom) });
    }
  }
  out.sort((a, b) => a.y0 - b.y0);
  return { docH, regions: out };
}, regionsDef);

if (!fs.existsSync(drPath)) {
  await page.screenshot({ path: drPath, fullPage: true });
}
if (!fs.existsSync(wpPath)) {
  if (fs.existsSync(refVp)) fs.copyFileSync(refVp, wpPath);
  else {
    console.error('Missing WP viewport:', wpPath);
    process.exit(1);
  }
}

await browser.close();

const wp = PNG.sync.read(fs.readFileSync(wpPath));
const dr = PNG.sync.read(fs.readFileSync(drPath));
const w = Math.min(wp.width, dr.width);
const h = Math.min(wp.height, dr.height);
const diff = new PNG({ width: w, height: h });
const mism = pixelmatch(wp.data, dr.data, diff.data, w, h, { threshold: 0.1, includeAA: true });
fs.writeFileSync(path.join(outDir, 'diff-heatmap.png'), PNG.sync.write(diff));
fs.copyFileSync(wpPath, path.join(outDir, 'wp-viewport-mac.png'));
fs.copyFileSync(drPath, path.join(outDir, 'drupal-viewport.png'));

const overall = mism / (w * h);
const bands = [];
const rowCount = 40;
const rh = Math.max(1, Math.floor(h / rowCount));
for (let r = 0; r < rowCount; r++) {
  const y0 = r * rh;
  const y1 = Math.min((r + 1) * rh, h);
  let cnt = 0;
  let tot = 0;
  for (let y = y0; y < y1; y++) {
    for (let x = 0; x < w; x++) {
      const i = (w * y + x) * 4;
      tot++;
      if (diff.data[i] || diff.data[i + 1] || diff.data[i + 2]) cnt++;
    }
  }
  bands.push({ row: r, y0, y1, ratio: tot ? cnt / tot : 0 });
}

const shift = estimateVerticalShift(wp, dr, w, h);
const cascade = detectCascade(bands, overall, shift);

const regions = [];
let cursor = 0;
for (const reg of regionBounds.regions) {
  const y0 = Math.min(Math.max(0, reg.y0), h);
  const y1 = Math.min(Math.max(y0 + 1, reg.y1), h);
  const ratio = diffStats(wp.data, dr.data, w, h, y0, y1);
  const share = ((y1 - y0) / h) * 100;
  const contrib = (ratio * 100 * share) / 100;
  regions.push({
    id: reg.id,
    label: reg.label,
    y: `${y0}-${y1}`,
    diffPercent: +(ratio * 100).toFixed(1),
    shareOfViewport: +share.toFixed(1),
    contributionToOverallPercent: +contrib.toFixed(1),
  });
  cursor = y1;
}
if (cursor < h) {
  const ratio = diffStats(wp.data, dr.data, w, h, cursor, h);
  const share = ((h - cursor) / h) * 100;
  regions.push({
    id: 'remainder',
    label: 'Remainder / tail',
    y: `${cursor}-${h}`,
    diffPercent: +(ratio * 100).toFixed(1),
    shareOfViewport: +share.toFixed(1),
    contributionToOverallPercent: +((ratio * 100 * share) / 100).toFixed(1),
  });
}

if (regions.filter((r) => r.shareOfViewport > 1).length === 0) {
  regions.length = 0;
  const slices = [
    { id: 'top', label: 'Top band (0–25%)' },
    { id: 'upper_mid', label: 'Upper-mid (25–50%)' },
    { id: 'lower_mid', label: 'Lower-mid (50–75%)' },
    { id: 'bottom', label: 'Bottom (75–100%)' },
  ];
  for (let i = 0; i < slices.length; i++) {
    const y0 = Math.floor((i * h) / 4);
    const y1 = Math.floor(((i + 1) * h) / 4);
    const ratio = diffStats(wp.data, dr.data, w, h, y0, y1);
    const share = 25;
    regions.push({
      ...slices[i],
      y: `${y0}-${y1}`,
      diffPercent: +(ratio * 100).toFixed(1),
      shareOfViewport: share,
      contributionToOverallPercent: +((ratio * 100 * share) / 100).toFixed(1),
    });
  }
}

const report = {
  generatedAt: new Date().toISOString(),
  path: pagePath,
  breakpoint: bp,
  width,
  viewportHeightPx: h,
  overallDiffPercent: +(overall * 100).toFixed(2),
  progressDir,
  qaEvidenceDir: path.relative(parityRoot, outDir),
  cascade,
  regions,
  topScanBands: [...bands].sort((a, b) => b.ratio - a.ratio).slice(0, 8).map((b) => ({
    y: `${b.y0}-${b.y1}`,
    diffPercent: +(b.ratio * 100).toFixed(1),
  })),
};

fs.writeFileSync(path.join(outDir, 'region-report.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
