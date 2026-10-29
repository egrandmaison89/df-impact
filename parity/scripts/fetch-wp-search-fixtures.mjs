#!/usr/bin/env node
/**
 * Capture live WP search HTML fixtures (?s= queries) for offline parity.
 *
 *   node scripts/fetch-wp-search-fixtures.mjs
 *   QUERIES=cancer,jimmy,fund,zzznomatch99 node scripts/fetch-wp-search-fixtures.mjs
 */
import { chromium } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { dismissOverlays, preparePage } from '../lib/page-utils.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const outRoot = path.join(parityRoot, 'cache', 'wp-search');
const wpBase = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');
const queries = (process.env.QUERIES || 'cancer,jimmy,fund,zzznomatch99')
  .split(',')
  .map((q) => q.trim())
  .filter(Boolean);

fs.mkdirSync(outRoot, { recursive: true });

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
await preparePage(page);

const manifest = { generatedAt: new Date().toISOString(), queries: {} };

for (const q of queries) {
  const url = `${wpBase}/?s=${encodeURIComponent(q)}`;
  let ok = false;
  let html = '';
  let metrics = {};
  for (let attempt = 1; attempt <= 4; attempt++) {
    try {
      await page.goto(url, { waitUntil: 'load', timeout: 120000 });
      await dismissOverlays(page);
      await page.waitForTimeout(1200);
      html = await page.content();
      const blocked = /captcha|sgcaptcha|security check/i.test(html);
      if (blocked) {
        await page.waitForTimeout(2000 * attempt);
        continue;
      }
      metrics = await page.evaluate(() => {
        const main = document.querySelector('main') || document.body;
        const cards = main.querySelectorAll(
          '.wp-block-post-template .wp-block-post, .search-results .card, .search-results article, .views-row',
        );
        const pager = main.querySelector('.pager, nav.pager, .pagination, .nav-links');
        const h1 = main.querySelector('h1');
        const empty = main.querySelector('.search-no-results, .no-results');
        return {
          title: document.title,
          h1: h1?.textContent?.trim() || null,
          resultCards: cards.length,
          hasPager: Boolean(pager),
          emptyText: empty?.textContent?.trim()?.slice(0, 200) || null,
          bodyClass: document.body.className,
        };
      });
      ok = true;
      break;
    }
    catch (e) {
      if (attempt === 4) {
        metrics = { error: String(e) };
      }
    }
  }
  const slug = q.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') || 'query';
  const htmlPath = path.join(outRoot, `${slug}.html`);
  if (ok && html) {
    fs.writeFileSync(htmlPath, html);
  }
  manifest.queries[q] = { ok, url, htmlFile: ok ? `cache/wp-search/${slug}.html` : null, metrics };
}

await browser.close();
const manifestPath = path.join(outRoot, 'manifest.json');
fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));
console.log(JSON.stringify({ manifestPath, manifest }, null, 2));
