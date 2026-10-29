#!/usr/bin/env node
/**
 * Captures editor guide screenshots (requires bootstrapped site + drush on PATH).
 */
import { execSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from '@playwright/test';

const base = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const outDir = path.resolve(process.cwd(), 'docs/editors/screenshots');
fs.mkdirSync(outDir, { recursive: true });

const uli = execSync(
  `cd drupal && ./vendor/bin/drush -r web uli --uri=${base} admin`,
  { encoding: 'utf8' },
).trim();

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await page.goto(uli);
await page.goto(`${base}/admin/content/issue-dashboard`, { waitUntil: 'networkidle' });
await page.screenshot({ path: path.join(outDir, 'issue-dashboard.png'), fullPage: true });

await page.goto(`${base}/admin/config/df-impact/site-settings`, { waitUntil: 'networkidle' });
await page.screenshot({ path: path.join(outDir, 'site-settings-homepage-hero.png'), fullPage: true });

await page.goto(`${base}/node/add/article`, { waitUntil: 'networkidle' });
await page.screenshot({ path: path.join(outDir, 'article-body-editor.png'), fullPage: true });

await browser.close();
console.log(JSON.stringify({ outDir, files: fs.readdirSync(outDir) }, null, 2));
