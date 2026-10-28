#!/usr/bin/env node
/**
 * Diff Drupal vs reference-pack; chrome = header clip + footer element screenshot.
 */
import { chromium } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { PNG } from 'pngjs';
import pixelmatch from 'pixelmatch';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const packRoot = path.join(parityRoot, 'reference-pack');
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const component = process.env.PROGRESS_COMPONENT || 'chrome-global';
const outRoot = path.join(parityRoot, 'reports', 'progress', component);

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

function loadHeaderClip(packSlug, bp, width) {
  const csPath = path.join(packRoot, packSlug, bp, 'computed-styles.json');
  if (!fs.existsSync(csPath)) return { x: 0, y: 0, width, height: 160 };
  const data = JSON.parse(fs.readFileSync(csPath, 'utf8'));
  const styles = data.styles || {};
  let header =
    bp === 'desktop'
      ? styles['.desktop-header'] || styles.header
      : styles['.mobile-header'] || styles.header;
  if (!header?.rect || header.rect.h < 2) {
    header = styles.header || styles['.desktop-header'] || styles['.mobile-header'];
  }
  if (!header?.rect || header.rect.h < 2) return { x: 0, y: 0, width, height: 160 };
  return {
    x: 0,
    y: 0,
    width,
    height: Math.min(900, Math.ceil(header.rect.h) + 4),
  };
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
  const clip = loadHeaderClip(packSlug, bp, width);
  return page.screenshot({ clip });
}

const browser = await chromium.launch();
const summary = [];

for (const p of PAGES) {
  const drPath = p === '/' ? '/' : p === '/homepage' ? '/homepage' : p.startsWith('/') ? p : `/${p}`;
  const packSlug = pathToPackSlug(p === '/homepage' ? '/homepage' : p);
  for (const [bp, width] of Object.entries(BPS)) {
    const refVp = path.join(packRoot, packSlug, bp, 'viewport.png');
    if (!fs.existsSync(refVp)) {
      summary.push({ path: p, bp, error: 'missing reference viewport' });
      continue;
    }
    const outDir = path.join(outRoot, `${packSlug}_${bp}`);
    fs.mkdirSync(outDir, { recursive: true });

    const refPage = await browser.newPage();
    await refPage.setViewportSize({ width, height: 900 });
    const refHtml = path.join(packRoot, packSlug, bp, 'rendered.html');
    await refPage.goto(`file://${refHtml}`, { waitUntil: 'domcontentloaded' });
    await refPage.waitForTimeout(800);
    await refPage.evaluate(() => document.fonts.ready);

    const drPage = await browser.newPage();
    await drPage.setViewportSize({ width, height: 900 });
    await drPage.goto(`${drupalBase}${drPath}`, { waitUntil: 'networkidle', timeout: 90000 }).catch(() => {});
    await drPage.waitForTimeout(1200);
    await drPage.evaluate(() => document.fonts.ready);

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
    const fullRatio = diffBuffers(refVpBuf, fs.readFileSync(drVp), path.join(outDir, 'diff-full.png'));
    fs.copyFileSync(refVp, path.join(outDir, 'wp-viewport.png'));

    const chromeRatio =
      footerRatio === null ? headerRatio : (headerRatio + footerRatio) / 2;

    summary.push({
      path: p,
      bp,
      pixelDiffRatio: fullRatio,
      headerPixelDiffRatio: headerRatio,
      footerPixelDiffRatio: footerRatio,
      chromePixelDiffRatio: chromeRatio,
    });

    await refPage.close();
    await drPage.close();
  }
}

const chromeRows = summary.filter((r) => typeof r.chromePixelDiffRatio === 'number');
const medians = {};
for (const bp of Object.keys(BPS)) {
  const vals = chromeRows.filter((r) => r.bp === bp).map((r) => r.chromePixelDiffRatio);
  if (vals.length) {
    vals.sort((a, b) => a - b);
    medians[`chrome_median_${bp}`] = vals[Math.floor(vals.length / 2)];
  }
}

fs.writeFileSync(
  path.join(outRoot, 'summary.json'),
  JSON.stringify({ component, drupalBase, pages: PAGES.length, medians, summary }, null, 2),
);
console.log(JSON.stringify({ medians, summary }, null, 2));
await browser.close();
