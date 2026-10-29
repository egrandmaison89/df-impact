#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDir = process.env.SCREENSHOT_OUT || '/opt/cursor/artifacts/screenshots';
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const wpBase = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');

const targets = [
  { name: 'drupal-building-the-future', url: `${drupalBase}/2026/09/building-the-future-one-ride-at-a-time` },
  { name: 'drupal-a-new-era', url: `${drupalBase}/2026/09/a-new-era-in-cancer-care-lavine-bekenstein-cancer-hospital-at-dana-farber` },
  { name: 'drupal-header-gtranslate', url: `${drupalBase}/2026/09/building-the-future-one-ride-at-a-time`, clip: 'header' },
  { name: 'wp-building-the-future-ref', url: `${wpBase}/2026/09/building-the-future-one-ride-at-a-time/` },
  { name: 'wp-a-new-era-ref', url: `${wpBase}/2026/09/a-new-era-in-cancer-care-lavine-bekenstein-cancer-hospital-at-dana-farber/` },
  { name: 'wp-header-gtranslate-ref', url: `${wpBase}/2026/09/building-the-future-one-ride-at-a-time/`, clip: 'header' },
];
const widths = [1440, 390];

fs.mkdirSync(outDir, { recursive: true });
const browser = await chromium.launch();
for (const w of widths) {
  const page = await browser.newPage({ viewport: { width: w, height: 900 } });
  for (const t of targets) {
    await page.goto(t.url, { waitUntil: 'domcontentloaded', timeout: 120000 });
    await page.waitForTimeout(600);
    const file = path.join(outDir, `${t.name}-${w}.png`);
    if (t.clip === 'header') {
      const el = page.locator('header').first();
      if (await el.count()) await el.screenshot({ path: file });
      else await page.screenshot({ path: file });
    } else {
      await page.screenshot({ path: file, fullPage: true });
    }
    console.log('saved', file);
  }
  await page.close();
}
await browser.close();
