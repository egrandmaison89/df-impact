#!/usr/bin/env node
/**
 * Header chrome box compare (logo, donate, header h) vs ref-pack or live WP.
 *   DRUPAL_BASE_URL=http://127.0.0.1:8888 node scripts/compare-header-chrome.mjs
 */
import { chromium } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { dismissOverlays, preparePage } from '../lib/page-utils.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const wpBase = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const scrollY = Number(process.env.PARITY_HEADER_SCROLL_Y || 300);
const tolerance = Number(process.env.PARITY_HEADER_TOLERANCE_PX || 4);

const paths = (process.env.PARITY_HEADER_PATHS || '/,/2022/11/couple-commits-2-million-to-fund-groundbreaking-clinical-trials,/category/digital-exclusives')
  .split(',')
  .map((p) => p.trim())
  .filter(Boolean);

const widths = (process.env.PARITY_HEADER_WIDTHS || '1440,1024,768,390')
  .split(',')
  .map((n) => Number(n.trim()))
  .filter((n) => n > 0);

async function measure(page) {
  return page.evaluate(() => {
    const pick = (sel) => {
      const el = document.querySelector(sel);
      if (!el) return null;
      const r = el.getBoundingClientRect();
      const img = el.querySelector('img');
      const ir = img?.getBoundingClientRect();
      return {
        x: Math.round(r.x * 10) / 10,
        y: Math.round(r.y * 10) / 10,
        w: Math.round(r.width * 10) / 10,
        h: Math.round(r.height * 10) / 10,
        imgW: ir ? Math.round(ir.width * 10) / 10 : null,
        imgH: ir ? Math.round(ir.height * 10) / 10 : null,
      };
    };
    const desktop = !!document.querySelector('.desktop-header')?.offsetParent;
    return {
      mode: desktop ? 'desktop' : 'mobile',
      header: pick('.desktop-header, .mobile-header'),
      logo: pick('.header-logo'),
      donate: pick('.desktop-header .header-button-section a, .mobile-header .header-button-section a'),
    };
  });
}

function delta(a, b) {
  if (!a || !b) return null;
  return {
    x: Math.round((a.x - b.x) * 10) / 10,
    y: Math.round((a.y - b.y) * 10) / 10,
    w: Math.round((a.w - b.w) * 10) / 10,
    h: Math.round((a.h - b.h) * 10) / 10,
  };
}

const browser = await chromium.launch();
const summary = [];

for (const pagePath of paths) {
  for (const width of widths) {
    for (const [label, base, useRef] of [
      ['wp', wpBase, false],
      ['drupal', drupalBase, false],
    ]) {
      const page = await browser.newPage({ viewport: { width, height: 900 } });
      if (label === 'drupal') await preparePage(page);
      const url = useRef
        ? `file://${path.join(parityRoot, 'reference-pack', 'homepage', 'desktop', 'rendered.html')}`
        : `${base}${pagePath === '/' ? '' : pagePath}`;
      try {
        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 120000 });
        if (label === 'drupal') await dismissOverlays(page);
        await page.waitForTimeout(600);
        await page.evaluate((y) => window.scrollTo(0, y), scrollY);
        await page.waitForTimeout(400);
        const row = { path: pagePath, width, site: label, scrollY, ...(await measure(page)) };
        summary.push(row);
      } catch (e) {
        summary.push({ path: pagePath, width, site: label, error: String(e) });
      }
      await page.close();
    }
  }
}

await browser.close();

const pairs = [];
for (const pagePath of paths) {
  for (const width of widths) {
    const wp = summary.find((r) => r.path === pagePath && r.width === width && r.site === 'wp' && r.logo);
    const dr = summary.find((r) => r.path === pagePath && r.width === width && r.site === 'drupal' && r.logo);
    if (wp && dr) {
      pairs.push({
        path: pagePath,
        width,
        logoDelta: delta(dr.logo, wp.logo),
        donateDelta: delta(dr.donate, wp.donate),
        headerDelta: delta(dr.header, wp.header),
        pass:
          Math.abs(delta(dr.logo, wp.logo)?.x ?? 999) <= tolerance &&
          Math.abs(delta(dr.donate, wp.donate)?.x ?? 999) <= tolerance,
      });
    }
  }
}

const out = { tolerance, scrollY, pairs, raw: summary };
console.log(JSON.stringify(out, null, 2));
const failed = pairs.filter((p) => !p.pass);
process.exitCode = failed.length ? 1 : 0;
