#!/usr/bin/env node
/** Measure computed padding on .wp-site-blocks, main, footer vs live WP at custom widths. */
import { chromium } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { preparePage, gotoWithBoundedSettle } from '../lib/page-utils.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const wpBase = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');
const widths = [1440, 1024, 820, 768, 390];
const paths = ['/', '/stories', '/in-brief'];

async function measure(page) {
  return page.evaluate(() => {
    const pick = (sel) => {
      const el = document.querySelector(sel);
      if (!el) return null;
      const cs = getComputedStyle(el);
      const r = el.getBoundingClientRect();
      return {
        paddingLeft: cs.paddingLeft,
        paddingRight: cs.paddingRight,
        marginLeft: cs.marginLeft,
        marginRight: cs.marginRight,
        width: cs.width,
        rectX: Math.round(r.x),
        rectW: Math.round(r.width),
      };
    };
    return {
      siteBlocks: pick('.wp-site-blocks'),
      main: pick('main#wp--skip-link--target'),
      footer: pick('.wp-site-blocks > footer'),
      outerVar: getComputedStyle(document.querySelector('.wp-site-blocks') || document.body)
        .getPropertyValue('--wp--custom--spacing--outer')
        .trim(),
    };
  });
}

const browser = await chromium.launch();
const out = { generatedAt: new Date().toISOString(), drupalBase, wpBase, rows: [] };

for (const w of widths) {
  for (const p of paths) {
    const wpPage = await browser.newPage();
    const drPage = await browser.newPage();
    await preparePage(wpPage);
    await preparePage(drPage);
    await wpPage.setViewportSize({ width: w, height: 900 });
    await drPage.setViewportSize({ width: w, height: 900 });
    const wpUrl = p === '/' ? wpBase : `${wpBase}${p}`;
    const drUrl = p === '/' ? drupalBase : `${drupalBase}${p}`;
    try {
      await gotoWithBoundedSettle(wpPage, wpUrl, { timeout: 120000 });
      await gotoWithBoundedSettle(drPage, drUrl, { timeout: 120000 });
      const wp = await measure(wpPage);
      const dr = await measure(drPage);
      out.rows.push({ width: w, path: p, wp, drupal: dr });
    } catch (e) {
      out.rows.push({ width: w, path: p, error: String(e) });
    }
    await wpPage.close();
    await drPage.close();
  }
}
await browser.close();

const outFile = path.join(parityRoot, 'reports', 'outer-padding-measure.json');
fs.mkdirSync(path.dirname(outFile), { recursive: true });
fs.writeFileSync(outFile, JSON.stringify(out, null, 2));
console.log(JSON.stringify({ outFile, rowCount: out.rows.length }, null, 2));
