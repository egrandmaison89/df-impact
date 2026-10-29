#!/usr/bin/env node
/** Drupal vs live WP viewport screenshots for PR verification artifacts. */
import { chromium } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { preparePage, gotoWithBoundedSettle, dismissOverlays } from '../lib/page-utils.mjs';
import { stabilizeParityViewport } from '../lib/capture-stabilize.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const wpBase = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');
const outRoot = process.env.ARTIFACT_DIR || '/opt/cursor/artifacts/pr31-visual-parity';
const widths = (process.env.CAPTURE_WIDTHS || '1440,1024,820,768,390').split(',').map(Number);

const scenarios = [
  { slug: 'article-share', drupal: '/2022/11/couple-commits-2-million-to-fund-groundbreaking-clinical-trials', wp: '/2022/11/couple-commits-2-million-to-fund-groundbreaking-clinical-trials' },
  { slug: 'issue-pager', drupal: '/fall-2025-issue', wp: '/fall-2025-issue' },
  { slug: 'stories-pager', drupal: '/stories', wp: '/stories' },
  { slug: 'in-brief', drupal: '/in-brief', wp: '/in-brief' },
  { slug: 'homepage', drupal: '/', wp: '/' },
];

fs.mkdirSync(outRoot, { recursive: true });
const browser = await chromium.launch();
const manifest = { generatedAt: new Date().toISOString(), outRoot, files: [] };

for (const sc of scenarios) {
  for (const w of widths) {
    for (const side of ['drupal', 'wp']) {
      const page = await browser.newPage();
      await preparePage(page);
      await page.setViewportSize({ width: w, height: 900 });
      const url = side === 'drupal' ? `${drupalBase}${sc.drupal}` : `${wpBase}${sc.wp}`;
      try {
        await gotoWithBoundedSettle(page, url, { timeout: 120000 });
        if (side === 'drupal') await dismissOverlays(page);
        await stabilizeParityViewport(page);
        const name = `${sc.slug}-w${w}-${side}.png`;
        const file = path.join(outRoot, name);
        await page.screenshot({ path: file, fullPage: false });
        manifest.files.push({ scenario: sc.slug, width: w, side, file });
      } catch (e) {
        manifest.files.push({ scenario: sc.slug, width: w, side, error: String(e) });
      }
      await page.close();
    }
  }
}
await browser.close();
fs.writeFileSync(path.join(outRoot, 'manifest.json'), JSON.stringify(manifest, null, 2));
console.log(JSON.stringify({ outRoot, count: manifest.files.length }, null, 2));
