#!/usr/bin/env node
/**
 * Assert Drupal mega-menu nav link background at a viewport width.
 * Usage: node scripts/check-drupal-nav-bg.mjs <path> <width> <expected-rgb>
 */
import { chromium } from '@playwright/test';

const path = process.argv[2];
const width = Number(process.argv[3] || 768);
const expected = process.argv[4] || 'rgb(255, 255, 255)';
const base = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const url = path === '/homepage' ? `${base}/` : `${base}${path}`;
const sel = '#mega-menu-max_mega_menu_1 > li.mega-menu-item > a.mega-menu-link';

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width, height: 900 } });
await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 120_000 });
const bg = await page.evaluate((s) => {
  const el = document.querySelector(s);
  return el ? getComputedStyle(el).backgroundColor : null;
}, sel);
await browser.close();

if (bg !== expected) {
  console.error(JSON.stringify({ path, width, expected, actual: bg, url }));
  process.exit(1);
}
console.log(JSON.stringify({ path, width, backgroundColor: bg, ok: true }));
