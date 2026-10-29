#!/usr/bin/env node
/**
 * Drupal vs reference-pack: structural gate (computed-styles.json) +
 * same-renderer (Linux) pixel diffs + optional Mac viewport.png baseline.
 */
import { chromium } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { PNG } from 'pngjs';
import pixelmatch from 'pixelmatch';
import { fileURLToPath } from 'node:url';
import { startRefPackServer } from './lib/ref-pack-server.mjs';
import { preparePage } from '../lib/page-utils.mjs';
import { stabilizeParityViewport } from '../lib/capture-stabilize.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const packRoot = path.join(parityRoot, 'reference-pack');
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const component = process.env.PROGRESS_COMPONENT || 'chrome-global';
const outRoot = path.join(parityRoot, 'reports', 'progress', component);
const RECT_TOLERANCE = Number(process.env.PARITY_RECT_TOLERANCE_PX || 2);

const STYLE_KEYS = [
  'display',
  'position',
  'top',
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
  'background-image',
  'border',
  'border-bottom',
  'box-shadow',
  'gap',
  'justify-content',
  'align-items',
  'max-width',
];

const extraSelectors = JSON.parse(
  fs.readFileSync(path.join(packRoot, 'chrome-structural-selectors.json'), 'utf8'),
).selectors;

function packSlugToPath(name) {
  if (name === 'root') return '/';
  if (name === 'homepage') return '/homepage';
  if (name.startsWith('category_')) return `/category/${name.slice('category_'.length).replace(/_/g, '-')}`;
  const dateMatch = name.match(/^(\d{4})_(\d{2})_(.+)$/);
  if (dateMatch) return `/${dateMatch[1]}/${dateMatch[2]}/${dateMatch[3].replace(/_/g, '-')}`;
  return `/${name.replace(/_/g, '-')}`;
}

const defaultPages = fs
  .readdirSync(packRoot, { withFileTypes: true })
  .filter(
    (d) =>
      d.isDirectory() &&
      d.name !== 'assets' &&
      !d.name.startsWith('phase') &&
      fs.existsSync(path.join(packRoot, d.name, 'desktop', 'viewport.png')),
  )
  .map((d) => packSlugToPath(d.name));

const PAGES = (process.env.PACK_COMPARE_PATHS || defaultPages.join(','))
  .split(',')
  .map((p) => p.trim())
  .filter(Boolean);
const BPS = { desktop: 1440, tablet: 768, mobile: 375 };

function pathToPackSlug(p) {
  if (p === '/' || p === '') return 'root';
  if (p === '/homepage') return 'homepage';
  if (p.startsWith('/category/')) {
    return `category_${p.slice('/category/'.length).replace(/-/g, '_')}`;
  }
  const m = p.match(/^\/(\d{4})\/(\d{2})\/(.+?)\/?$/);
  if (m) return `${m[1]}_${m[2]}_${m[3]}`;
  return p.replace(/^\//, '').replace(/\//g, '_');
}

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

function diffBuffers(aBuf, bBuf, diffPath) {
  const a = PNG.sync.read(aBuf);
  const b = PNG.sync.read(bBuf);
  const w = Math.min(a.width, b.width);
  const h = Math.min(a.height, b.height);
  const ca = new PNG({ width: w, height: h });
  const cb = new PNG({ width: w, height: h });
  PNG.bitblt(a, ca, 0, 0, w, h, 0, 0);
  PNG.bitblt(b, cb, 0, 0, w, h, 0, 0);
  const diff = new PNG({ width: w, height: h });
  const mism = pixelmatch(ca.data, cb.data, diff.data, w, h, { threshold: 0.1, includeAA: true });
  fs.mkdirSync(path.dirname(diffPath), { recursive: true });
  fs.writeFileSync(diffPath, PNG.sync.write(diff));
  return mism / (w * h);
}

async function measureElement(page, selector) {
  const el = page.locator(selector).first();
  if (!(await el.count())) return null;
  const box = await el.boundingBox();
  if (!box || box.width < 1 || box.height < 1) return null;
  return el.evaluate(
    (node, keys) => {
      const cs = getComputedStyle(node);
      const r = node.getBoundingClientRect();
      const styles = {};
      for (const k of keys) styles[k] = cs[k];
      return { rect: { x: r.x, y: r.y, w: r.width, h: r.height }, styles };
    },
    STYLE_KEYS,
  );
}

function compareRect(expected, actual, selector, failures) {
  for (const dim of ['x', 'y', 'w', 'h']) {
    const exp = expected[dim];
    const got = actual[dim];
    if (typeof exp !== 'number' || typeof got !== 'number') continue;
    if (Math.abs(got - exp) > RECT_TOLERANCE) {
      failures.push({ selector, kind: 'rect', dim, expected: exp, actual: got });
    }
  }
}

function compareStyles(spec, live, selector, failures) {
  const skipDims = spec.rect ? new Set(['width', 'height']) : new Set();
  for (const key of STYLE_KEYS) {
    if (skipDims.has(key)) continue;
    if (spec[key] === undefined) continue;
    const exp = normalizeStyle(key, spec[key]);
    const got = normalizeStyle(key, live.styles[key]);
    if (exp !== got) {
      failures.push({ selector, kind: 'style', key, expected: exp, actual: got });
    }
  }
}

const chromeSelectorSet = new Set(extraSelectors);

async function structuralGate(page, packSlug, bp) {
  const csPath = path.join(packRoot, packSlug, bp, 'computed-styles.json');
  const failures = [];
  let checked = 0;
  if (fs.existsSync(csPath)) {
    const data = JSON.parse(fs.readFileSync(csPath, 'utf8'));
    const expected = data.styles || {};
    for (const [selector, spec] of Object.entries(expected)) {
      if (!chromeSelectorSet.has(selector)) continue;
      if (!spec?.rect) continue;
      const live = await measureElement(page, selector);
      if (!live) {
        failures.push({ selector, kind: 'missing_element' });
        continue;
      }
      checked++;
      compareRect(spec.rect, live.rect, selector, failures);
      compareStyles(spec, live, selector, failures);
    }
  }
  for (const selector of extraSelectors) {
    const live = await measureElement(page, selector);
    if (!live) continue;
    checked++;
  }
  return { checked, failures, pass: failures.length === 0 };
}

async function structuralGateRefVsDrupal(refPage, drPage, packSlug, bp) {
  const failures = [];
  let checked = 0;
  const csPath = path.join(packRoot, packSlug, bp, 'computed-styles.json');
  const fromPack = fs.existsSync(csPath)
    ? Object.keys(JSON.parse(fs.readFileSync(csPath, 'utf8')).styles || {}).filter((s) =>
        chromeSelectorSet.has(s),
      )
    : [];
  const selectors = [...new Set([...fromPack, ...extraSelectors])];
  for (const selector of selectors) {
    const refLive = await measureElement(refPage, selector);
    const drLive = await measureElement(drPage, selector);
    if (!refLive && !drLive) continue;
    if (!refLive || !drLive) {
      failures.push({ selector, kind: 'presence_mismatch', ref: !!refLive, drupal: !!drLive });
      continue;
    }
    checked++;
    compareRect(refLive.rect, drLive.rect, selector, failures);
    for (const key of STYLE_KEYS) {
      if (key === 'width' || key === 'height') continue;
      const exp = normalizeStyle(key, refLive.styles[key]);
      const got = normalizeStyle(key, drLive.styles[key]);
      if (exp !== got) {
        failures.push({ selector, kind: 'ref_vs_drupal_style', key, expected: exp, actual: got });
      }
    }
  }
  return { checked, failures, pass: failures.length === 0 };
}

async function screenshotHeaderRegion(page, bp, packSlug, width) {
  const sel = bp === 'desktop' ? '.desktop-header' : '.mobile-header';
  const el = page.locator(sel).first();
  if (await el.count()) {
    const box = await el.boundingBox();
    if (box && box.width > 10 && box.height > 10) {
      return el.screenshot({ timeout: 15000 });
    }
  }
  const csPath = path.join(packRoot, packSlug, bp, 'computed-styles.json');
  let height = 160;
  if (fs.existsSync(csPath)) {
    const data = JSON.parse(fs.readFileSync(csPath, 'utf8'));
    const styles = data.styles || {};
    let header =
      bp === 'desktop'
        ? styles['.desktop-header'] || styles.header
        : styles['.mobile-header'] || styles.header;
    if (header?.rect?.h >= 2) height = Math.min(900, Math.ceil(header.rect.h) + 4);
  }
  return page.screenshot({ clip: { x: 0, y: 0, width, height } });
}

const useLinuxRef = process.env.PARITY_COMPARE_LINUX === '1';
const browser = await chromium.launch();
const summary = [];

async function runMacPackCompare() {
  for (const p of PAGES) {
    const drPath = p === '/' ? '/' : p.startsWith('/') ? p : `/${p}`;
    const packSlug = pathToPackSlug(p === '/homepage' ? '/homepage' : p);
    for (const [bp, width] of Object.entries(BPS)) {
      const refVp = path.join(packRoot, packSlug, bp, 'viewport.png');
      if (!fs.existsSync(refVp)) {
        summary.push({ path: p, bp, error: 'missing reference viewport' });
        continue;
      }
      const outDir = path.join(outRoot, `${packSlug}_${bp}`);
      fs.mkdirSync(outDir, { recursive: true });

      const refHtml = path.join(packRoot, packSlug, bp, 'rendered.html');
      const refPage = await browser.newPage();
      await preparePage(refPage);
      await refPage.setViewportSize({ width, height: 900 });
      await refPage.goto(`file://${refHtml}`, { waitUntil: 'domcontentloaded', timeout: 120000 });
      await stabilizeParityViewport(refPage);

      const drPage = await browser.newPage();
      await preparePage(drPage);
      await drPage.setViewportSize({ width, height: 900 });
      await drPage.goto(`${drupalBase}${drPath}`, { waitUntil: 'networkidle', timeout: 120000 }).catch(() => {});
      await stabilizeParityViewport(drPage);

      const refHeader = await screenshotHeaderRegion(refPage, bp, packSlug, width);
      const drHeader = await screenshotHeaderRegion(drPage, bp, packSlug, width);
      fs.writeFileSync(path.join(outDir, 'wp-header.png'), refHeader);
      fs.writeFileSync(path.join(outDir, 'drupal-header.png'), drHeader);
      const headerRatio = diffBuffers(refHeader, drHeader, path.join(outDir, 'diff-header.png'));

      let footerRatio = null;
      const refFooterEl = refPage.locator('footer').first();
      const drFooterEl = drPage.locator('footer').first();
      if ((await refFooterEl.count()) && (await drFooterEl.count())) {
        await refFooterEl.scrollIntoViewIfNeeded();
        await drFooterEl.scrollIntoViewIfNeeded();
        await refPage.waitForTimeout(200);
        await drPage.waitForTimeout(200);
        const refFooter = await refFooterEl.screenshot();
        const drFooter = await drFooterEl.screenshot();
        fs.writeFileSync(path.join(outDir, 'wp-footer.png'), refFooter);
        fs.writeFileSync(path.join(outDir, 'drupal-footer.png'), drFooter);
        footerRatio = diffBuffers(refFooter, drFooter, path.join(outDir, 'diff-footer.png'));
      }

      const refVpBuf = fs.readFileSync(refVp);
      const drVp = path.join(outDir, 'drupal-viewport.png');
      await drPage.screenshot({ path: drVp });
      const fullRatio = diffBuffers(refVpBuf, fs.readFileSync(drVp), path.join(outDir, 'diff-viewport-mac.png'));
      fs.copyFileSync(refVp, path.join(outDir, 'wp-viewport-mac.png'));

      const chromeRatio = footerRatio === null ? headerRatio : (headerRatio + footerRatio) / 2;

      summary.push({
        path: p,
        bp,
        pixelDiffRatio: fullRatio,
        headerPixelDiffRatio: headerRatio,
        footerPixelDiffRatio: footerRatio,
        chromePixelDiffRatio: chromeRatio,
        viewportPixelDiffRatioMac: fullRatio,
      });

      await refPage.close();
      await drPage.close();
    }
  }
}

const refServer = useLinuxRef ? await startRefPackServer(packRoot, { drupalBase: drupalBase }) : null;

try {
  if (!useLinuxRef) {
    await runMacPackCompare();
  } else for (const p of PAGES) {
    const drPath = p === '/' ? '/' : p.startsWith('/') ? p : `/${p}`;
    const packSlug = pathToPackSlug(p === '/homepage' ? '/homepage' : p);
    for (const [bp, width] of Object.entries(BPS)) {
      const macVp = path.join(packRoot, packSlug, bp, 'viewport.png');
      const outDir = path.join(outRoot, `${packSlug}_${bp}`);
      fs.mkdirSync(outDir, { recursive: true });

      const refUrl = `${refServer.baseUrl}/rendered/${packSlug}/${bp}`;
      const refPage = await browser.newPage();
      await preparePage(refPage);
      await refPage.setViewportSize({ width, height: 900 });
      await refPage.goto(refUrl, { waitUntil: 'networkidle', timeout: 120000 });
      await stabilizeParityViewport(refPage);

      const drPage = await browser.newPage();
      await preparePage(drPage);
      await drPage.setViewportSize({ width, height: 900 });
      await drPage.goto(`${drupalBase}${drPath}`, { waitUntil: 'networkidle', timeout: 120000 }).catch(() => {});
      await stabilizeParityViewport(drPage);

      const structuralPack = await structuralGate(drPage, packSlug, bp);
      const structuralRef = await structuralGateRefVsDrupal(refPage, drPage, packSlug, bp);

      const refHeader = await screenshotHeaderRegion(refPage, bp, packSlug, width);
      const drHeader = await screenshotHeaderRegion(drPage, bp, packSlug, width);
      fs.writeFileSync(path.join(outDir, 'ref-linux-header.png'), refHeader);
      fs.writeFileSync(path.join(outDir, 'drupal-header.png'), drHeader);
      const headerLinux = diffBuffers(refHeader, drHeader, path.join(outDir, 'diff-header-linux.png'));

      let footerLinux = null;
      const refFooterEl = refPage.locator('footer').first();
      const drFooterEl = drPage.locator('footer').first();
      if ((await refFooterEl.count()) && (await drFooterEl.count())) {
        await refFooterEl.scrollIntoViewIfNeeded();
        await drFooterEl.scrollIntoViewIfNeeded();
        await refPage.waitForTimeout(200);
        await drPage.waitForTimeout(200);
        const refFooter = await refFooterEl.screenshot();
        const drFooter = await drFooterEl.screenshot();
        fs.writeFileSync(path.join(outDir, 'ref-linux-footer.png'), refFooter);
        fs.writeFileSync(path.join(outDir, 'drupal-footer.png'), drFooter);
        footerLinux = diffBuffers(refFooter, drFooter, path.join(outDir, 'diff-footer-linux.png'));
      }

      const refVpBuf = await refPage.screenshot({ fullPage: false });
      const drVp = path.join(outDir, 'drupal-viewport.png');
      await drPage.screenshot({ path: drVp });
      const viewportLinux = diffBuffers(refVpBuf, fs.readFileSync(drVp), path.join(outDir, 'diff-viewport-linux.png'));
      fs.writeFileSync(path.join(outDir, 'ref-linux-viewport.png'), refVpBuf);

      let macViewportRatio = null;
      if (fs.existsSync(macVp)) {
        macViewportRatio = diffBuffers(fs.readFileSync(macVp), fs.readFileSync(drVp), path.join(outDir, 'diff-viewport-mac.png'));
        fs.copyFileSync(macVp, path.join(outDir, 'wp-viewport-mac.png'));
      }

      const chromeLinux =
        footerLinux === null ? headerLinux : (headerLinux + footerLinux) / 2;

      summary.push({
        path: p,
        bp,
        structuralPackPass: structuralPack.pass,
        structuralPackFailures: structuralPack.failures.length,
        structuralRefDrupalPass: structuralRef.pass,
        structuralRefDrupalFailures: structuralRef.failures.length,
        headerPixelDiffRatioLinux: headerLinux,
        footerPixelDiffRatioLinux: footerLinux,
        chromePixelDiffRatioLinux: chromeLinux,
        viewportPixelDiffRatioLinux: viewportLinux,
        viewportPixelDiffRatioMac: macViewportRatio,
        structuralPackSample: structuralPack.failures.slice(0, 5),
        structuralRefSample: structuralRef.failures.slice(0, 5),
      });

      await refPage.close();
      await drPage.close();
    }
  }
} finally {
  await browser.close();
  if (refServer) {
    await refServer.close();
  }
}

const chromeKey = useLinuxRef ? 'chromePixelDiffRatioLinux' : 'chromePixelDiffRatio';
const chromeRows = summary.filter((r) => typeof r[chromeKey] === 'number');
const medians = {};
for (const bp of Object.keys(BPS)) {
  const vals = chromeRows.filter((r) => r.bp === bp).map((r) => r[chromeKey]);
  if (vals.length) {
    vals.sort((a, b) => a - b);
    const mid = vals[Math.floor(vals.length / 2)];
    if (useLinuxRef) {
      medians[`chrome_median_linux_${bp}`] = mid;
    } else {
      medians[`chrome_median_${bp}`] = mid;
    }
  }
  const viewportKey = useLinuxRef ? 'viewportPixelDiffRatioMac' : 'viewportPixelDiffRatioMac';
  const vpVals = summary
    .filter((r) => r.bp === bp && typeof r[viewportKey] === 'number')
    .map((r) => r[viewportKey]);
  if (vpVals.length) {
    vpVals.sort((a, b) => a - b);
    medians[`viewport_median_mac_${bp}`] = vpVals[Math.floor(vpVals.length / 2)];
  }
}

const structuralPackPassAll = useLinuxRef ? summary.every((r) => r.structuralPackPass) : true;
const structuralRefPassAll = useLinuxRef ? summary.every((r) => r.structuralRefDrupalPass) : true;

const report = {
  component,
  drupalBase,
  refRenderer: useLinuxRef ? 'linux-local-rendered-html' : 'mac-pack-viewport-png',
  pages: PAGES.length,
  rectTolerancePx: RECT_TOLERANCE,
  structuralPackPassAll,
  structuralRefDrupalPassAll: structuralRefPassAll,
  medians,
  summary,
};

fs.writeFileSync(path.join(outRoot, 'summary.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify({ refRenderer: report.refRenderer, structuralPackPassAll, structuralRefDrupalPassAll: structuralRefPassAll, medians }, null, 2));

const gate = process.env.PARITY_FAIL_ON_GATE === '1';
const chromeFail = Object.values(medians).some((v) => typeof v === 'number' && v > 0.05);
if (gate && (!structuralRefPassAll || chromeFail)) {
  process.exitCode = 1;
}
