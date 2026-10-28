#!/usr/bin/env node
/**
 * Diff Drupal viewport PNGs against parity/reference-pack (owner Mac authority).
 * Writes parity/reports/progress/<component>/<slug>_<bp>.{json,diff.png}
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

const PAGES = (process.env.PACK_COMPARE_PATHS || '/homepage')
  .split(',')
  .map((p) => p.trim())
  .filter(Boolean);
const BPS = { desktop: 1440, tablet: 768, mobile: 375 };

const slug = (p) => (p === '/' ? 'root' : p.replace(/^\/|\/$/g, '').replace(/\//g, '_'));

function diffRatio(aPath, bPath, diffPath) {
  const a = PNG.sync.read(fs.readFileSync(aPath));
  const b = PNG.sync.read(fs.readFileSync(bPath));
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

const browser = await chromium.launch();
const summary = [];

for (const p of PAGES) {
  const drPath = p === '/homepage' ? '/homepage' : p.startsWith('/') ? p : `/${p}`;
  for (const [bp, width] of Object.entries(BPS)) {
    const refVp = path.join(packRoot, slug(p), bp, 'viewport.png');
    if (!fs.existsSync(refVp)) {
      summary.push({ path: p, bp, error: 'missing reference viewport' });
      continue;
    }
    const page = await browser.newPage();
    await page.setViewportSize({ width, height: 900 });
    await page.goto(`${drupalBase}${drPath}`, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForTimeout(800);
    const outDir = path.join(outRoot, `${slug(p)}_${bp}`);
    fs.mkdirSync(outDir, { recursive: true });
    const drVp = path.join(outDir, 'drupal-viewport.png');
    await page.screenshot({ path: drVp });
    const ratio = diffRatio(refVp, drVp, path.join(outDir, 'diff.png'));
    fs.copyFileSync(refVp, path.join(outDir, 'wp-viewport.png'));
    summary.push({ path: p, bp, pixelDiffRatio: ratio });
    await page.close();
  }
}

fs.writeFileSync(path.join(outRoot, 'summary.json'), JSON.stringify({ component, drupalBase, summary }, null, 2));
console.log(JSON.stringify(summary, null, 2));
await browser.close();
