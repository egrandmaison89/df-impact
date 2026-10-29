#!/usr/bin/env node
/**
 * Block-by-block main height diff: live WP vs Drupal for article samples.
 *
 *   DRUPAL_BASE_URL=http://127.0.0.1:8888 node scripts/compare-article-dom-blocks.mjs
 *   PARITY_ARTICLE_PATHS=/2022/11/couple...,/2026/09/american... node scripts/compare-article-dom-blocks.mjs
 */
import { chromium } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { dismissOverlays, preparePage } from '../lib/page-utils.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const wpBase = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const tolerance = Number(process.env.PARITY_MAIN_H_TOLERANCE_PX || 20);
const runId = process.env.REPORT_RUN_ID || String(Date.now());
const outDir = path.join(parityRoot, 'reports', 'article-dom-blocks', runId);

const defaultPaths = JSON.parse(
  fs.readFileSync(path.join(parityRoot, 'config', 'template-samples.json'), 'utf8'),
).article.paths.concat([
  '/2024/09/wheeling-in-the-years-the-pan-mass-challenge-produces-origin-story-video',
]);

const paths = (process.env.PARITY_ARTICLE_PATHS || defaultPaths.join(','))
  .split(',')
  .map((p) => p.trim())
  .filter(Boolean);

async function measure(page) {
  return page.evaluate(() => {
    const main = document.querySelector('main');
    const blocks = [];
    const root = main || document.querySelector('.wp-site-blocks');
    const walk = (el, depth = 0) => {
      if (!el || depth > 4) return;
      const r = el.getBoundingClientRect();
      if (r.height < 2 && el.children.length === 0) return;
      const cls = (el.className || '').toString().slice(0, 100);
      const tag = el.tagName.toLowerCase();
      const key =
        el.id ||
        (cls.includes('entry-content') ? 'entry-content' : '') ||
        (cls.includes('jp-related') ? 'jp-relatedposts' : '') ||
        (cls.includes('wp-block-post-terms') ? 'post-terms' : '') ||
        (cls.includes('wp-block-spacer') ? 'spacer' : '') ||
        (cls.includes('ss-inline-share') ? 'social-share' : '') ||
        `${tag}.${cls.split(' ')[0] || 'node'}`;
      blocks.push({
        key,
        tag,
        className: cls,
        h: Math.round(r.height * 10) / 10,
        y: Math.round(r.y * 10) / 10,
      });
      for (const child of el.children) {
        walk(child, depth + 1);
      }
    };
    if (main) {
      for (const child of main.children) {
        walk(child, 1);
      }
    }
    return {
      mainH: main ? Math.round(main.getBoundingClientRect().height * 10) / 10 : null,
      blocks,
    };
  });
}

const browser = await chromium.launch();
const summary = [];

for (const p of paths) {
  const row = { path: p, wp: null, drupal: null, mainDelta: null, pass: false };
  for (const [label, base] of [
    ['wp', wpBase],
    ['drupal', drupalBase],
  ]) {
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await preparePage(page);
    try {
      await page.goto(`${base}${p}`, { waitUntil: 'load', timeout: 120000 });
      await dismissOverlays(page);
      await page.waitForTimeout(900);
      row[label] = await measure(page);
    }
    catch (e) {
      row[label] = { error: String(e) };
    }
    await page.close();
  }
  if (row.wp?.mainH != null && row.drupal?.mainH != null) {
    row.mainDelta = Math.round((row.wp.mainH - row.drupal.mainH) * 10) / 10;
    row.pass = Math.abs(row.mainDelta) <= tolerance;
  }
  summary.push(row);
  console.log(JSON.stringify(row, null, 2));
}

await browser.close();

fs.mkdirSync(outDir, { recursive: true });
fs.writeFileSync(
  path.join(outDir, 'summary.json'),
  JSON.stringify({ tolerance, paths, summary }, null, 2),
);

const failed = summary.filter((r) => r.mainDelta != null && !r.pass);
process.exitCode = failed.length ? 1 : 0;
console.log(`\nmain h tolerance ${tolerance}px: ${summary.length - failed.length}/${summary.length} pass`);
