#!/usr/bin/env node
/**
 * Fetch live WordPress category/tag archive HTML via Playwright (captcha bypass).
 * Writes desktop/rendered.html into parity/reference-pack/{slug}/.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';
import { dismissOverlays, gotoWithBoundedSettle, preparePage } from '../lib/page-utils.mjs';
import { pathToPackSlug, normalizePath } from '../lib/content-sweep-path.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const manifest = JSON.parse(
  fs.readFileSync(path.join(parityRoot, 'manifest/site-manifest.json'), 'utf8'),
);
const wpBase = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');
const packRoot = path.join(parityRoot, 'reference-pack');
const retries = Number(process.env.PARITY_LIVE_RETRIES || '3');

const paths = (manifest.urls || [])
  .filter((e) => e.template === 'archive')
  .map((e) => e.path)
  .filter((p) => p && !/\/feed\/?$/i.test(p) && p !== '/feed' && p !== '/comments/feed');

const extra = ['/stories', '/topics'];
for (const p of extra) {
  if (!paths.includes(p)) paths.push(p);
}

fs.mkdirSync(packRoot, { recursive: true });

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await preparePage(page);

const results = [];
for (const p of paths) {
  const slug = pathToPackSlug(normalizePath(p));
  const dir = path.join(packRoot, slug, 'desktop');
  fs.mkdirSync(dir, { recursive: true });
  const outFile = path.join(dir, 'rendered.html');
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
  results.push({ path: p, slug, ok, outFile: ok ? outFile : null, error: ok ? null : lastErr });
  console.log(ok ? 'ok' : 'FAIL', p);
}

await browser.close();
const failed = results.filter((r) => !r.ok);
console.log(JSON.stringify({ packRoot, ok: results.length - failed.length, failed: failed.length }, null, 2));
process.exit(failed.length ? 1 : 0);
