#!/usr/bin/env node
/**
 * Screenshot + structural compare: WP ref ?s=cancer vs Drupal /search?s=cancer.
 */
import { chromium } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { startRefPackServer } from './lib/ref-pack-server.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const outDir = path.join(parityRoot, 'reports', 'progress', 'secondary-surfaces');
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const wpBase = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');

fs.mkdirSync(outDir, { recursive: true });

const refServer = await startRefPackServer(path.join(parityRoot, 'reference-pack'));
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });

async function mainMetrics(url, label) {
  await page.goto(url, { waitUntil: 'load', timeout: 120000 });
  await page.waitForTimeout(800);
  const shot = path.join(outDir, `search-cancer-${label}.png`);
  await page.screenshot({ path: shot, fullPage: false });
  return page.evaluate(() => {
    const main = document.querySelector('main') || document.body;
    const header = main.querySelector('h1, .search-page-header h1');
    const cards = main.querySelectorAll('.search-results .card, .search-results .node, .views-row, .postx-card');
    const pager = main.querySelector('.pager, nav.pager, .pagination');
    return {
      title: document.title,
      h1: header?.textContent?.trim() || null,
      resultNodes: cards.length,
      hasPager: Boolean(pager),
      bodyClass: document.body.className,
    };
  });
}

const dr = await mainMetrics(`${drupalBase}/search?s=cancer`, 'drupal');
let wp = { skipped: true, reason: 'live WP blocked' };
try {
  wp = await mainMetrics(`${wpBase}/?s=cancer`, 'wp-live');
} catch (e) {
  wp = { skipped: true, error: String(e) };
}

// Reference pack search page (empty query WP shell) for layout baseline.
let ref = { skipped: true };
try {
  ref = await mainMetrics(`${refServer.baseUrl}/search/desktop/rendered.html`, 'ref-pack-empty');
} catch (e) {
  ref = { skipped: true, error: String(e) };
}

await browser.close();
await refServer.close();

const report = {
  generatedAt: new Date().toISOString(),
  drupal: dr,
  wpLive: wp,
  refPackSearchShell: ref,
  screenshots: {
    drupal: 'search-cancer-drupal.png',
    wp: wp.skipped ? null : 'search-cancer-wp-live.png',
    refPack: 'search-cancer-ref-pack-empty.png',
  },
};
const outFile = path.join(outDir, `search-cancer-compare-${Date.now()}.json`);
fs.writeFileSync(outFile, JSON.stringify(report, null, 2));
console.log(JSON.stringify({ outFile, report }, null, 2));
