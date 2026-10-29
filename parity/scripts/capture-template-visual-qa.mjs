#!/usr/bin/env node
/**
 * Capture Drupal + live WP viewport screenshots for template/listing QA (1440/768/375).
 */
import { chromium } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { stabilizeParityViewport } from '../lib/capture-stabilize.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const wpBase = 'https://danafarberimpact.org';
const outRoot = path.join(
  parityRoot,
  'reports',
  'visual-qa',
  process.env.VISUAL_QA_COMPONENT || 'templates-inbrief-archives',
);
const BPS = { desktop: 1440, tablet: 768, mobile: 375 };

const samples = JSON.parse(
  fs.readFileSync(path.join(parityRoot, 'config', 'template-samples.json'), 'utf8'),
);
const listingExtras = [
  '/stories',
  '/topics',
  '/category/stories',
  '/issues',
  '/digital-exclusives',
];
const paths = [
  ...new Set([...Object.values(samples).flatMap((e) => e.paths || []), ...listingExtras]),
];

function slug(p) {
  return (p === '/' ? 'home' : p.replace(/^\//, '').replace(/\//g, '_')).replace(/[^a-zA-Z0-9_-]/g, '-');
}

async function capture(page, url, outFile) {
  await page.goto(url, { waitUntil: 'load', timeout: 120000 }).catch(() => {});
  await page.waitForTimeout(800);
  await stabilizeParityViewport(page);
  await page.screenshot({ path: outFile, fullPage: false });
}

const browser = await chromium.launch();
for (const p of paths) {
  for (const [bp, width] of Object.entries(BPS)) {
    const dir = path.join(outRoot, slug(p), bp);
    fs.mkdirSync(dir, { recursive: true });
    const height = bp === 'mobile' ? 1200 : 1600;
    const context = await browser.newContext({ viewport: { width, height } });
    const drPage = await context.newPage();
    const wpPage = await context.newPage();
    await capture(drPage, `${drupalBase}${p}`, path.join(dir, 'drupal-viewport.png'));
    await capture(wpPage, `${wpBase}${p}`, path.join(dir, 'wordpress-viewport.png'));
    await context.close();
  }
}
await browser.close();
console.log(JSON.stringify({ outRoot, paths: paths.length }, null, 2));
