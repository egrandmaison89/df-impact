#!/usr/bin/env node
/** Fetch live WordPress issue landing HTML via Playwright (bot/captcha bypass). */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';
import { dismissOverlays, gotoWithBoundedSettle, preparePage } from '../lib/page-utils.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const manifest = JSON.parse(fs.readFileSync(path.join(parityRoot, 'manifest/site-manifest.json'), 'utf8'));
const wpBase = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');
const outDir = path.join(parityRoot, 'cache/wp-issue-pages');
const retries = Number(process.env.PARITY_LIVE_RETRIES || '3');

const paths = (manifest.urls || [])
  .filter((e) => e.template === 'issue')
  .map((e) => e.path)
  .filter((p) => p && !/challenge/i.test(p));

fs.mkdirSync(outDir, { recursive: true });

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await preparePage(page);

const results = [];
for (const p of paths) {
  const slug = p.replace(/^\/|\/$/g, '').replace(/\//g, '_');
  const outFile = path.join(outDir, `${slug}.html`);
  const url = `${wpBase}${p}${p.endsWith('/') ? '' : '/'}`;
  let ok = false;
  let lastErr = '';
  for (let attempt = 0; attempt <= retries; attempt++) {
    try {
      await gotoWithBoundedSettle(page, url, { timeout: 120000 });
      await dismissOverlays(page);
      const html = await page.content();
      if (html.length < 5000 || /sgcaptcha|well-known\/sgcaptcha/i.test(html)) {
        throw new Error('captcha or short body');
      }
      fs.writeFileSync(outFile, html);
      ok = true;
      break;
    } catch (e) {
      lastErr = String(e.message || e);
      await page.waitForTimeout(1500 * (attempt + 1));
    }
  }
  results.push({ path: p, ok, outFile: ok ? outFile : null, error: ok ? null : lastErr });
}

await browser.close();
const failed = results.filter((r) => !r.ok);
console.log(JSON.stringify({ outDir, ok: results.length - failed.length, failed: failed.length, results }, null, 2));
process.exit(failed.length ? 1 : 0);
