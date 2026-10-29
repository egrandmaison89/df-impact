#!/usr/bin/env node
/**
 * Refresh reference-pack archive HTML + archive_page_wp_order.json from live WP.
 * Usage: node scripts/refresh-archive-refpack-from-live.mjs [/category/digital-exclusives ...]
 */
import { chromium } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';
import { normalizePath, pathToPackSlug } from '../lib/content-sweep-path.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const packRoot = path.join(parityRoot, 'reference-pack');
const wpBase = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');

const paths = process.argv.slice(2).length
  ? process.argv.slice(2).map((p) => normalizePath(p))
  : ['/category/digital-exclusives'];

const viewports = [
  ['desktop', { width: 1440, height: 900 }],
  ['tablet', { width: 768, height: 1024 }],
  ['mobile', { width: 375, height: 812 }],
];

const browser = await chromium.launch();
for (const publicPath of paths) {
  const slug = pathToPackSlug(publicPath);
  for (const [bp, size] of viewports) {
    const page = await browser.newPage();
    await page.setViewportSize(size);
    const url = `${wpBase}${publicPath === '/' ? '' : publicPath}`;
    const resp = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 120000 }).catch(() => null);
    const status = resp?.status() ?? 0;
    process.stderr.write(`fetch ${publicPath} ${bp} status=${status}\n`);
    const html = await page.content();
    const dir = path.join(packRoot, slug, bp);
    fs.mkdirSync(dir, { recursive: true });
    fs.writeFileSync(path.join(dir, 'rendered.html'), html);
    await page.close();
  }
}
await browser.close();

spawnSync('node', ['scripts/build-archive-order-fixture.mjs'], {
  cwd: parityRoot,
  stdio: 'inherit',
});
console.log('refreshed', paths.join(', '));
