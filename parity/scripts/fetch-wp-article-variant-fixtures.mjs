#!/usr/bin/env node
/**
 * Capture live WP article HTML for variant sample paths (captcha retry).
 *
 *   node scripts/fetch-wp-article-variant-fixtures.mjs
 */
import { chromium } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { dismissOverlays, preparePage } from '../lib/page-utils.mjs';
import { stabilizeParityViewport } from '../lib/capture-stabilize.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const outRoot = path.join(parityRoot, 'cache', 'wp-article-variants');
const classificationPath = path.join(
  parityRoot,
  'reports/progress/article-variants-parity/variant-classification.json',
);
const wpBase = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');
const VARIANT_KEYS = (
  process.env.VARIANT_KEYS ||
  'hero,no_hero,sidebar,pull_quote,gallery,video_embed,long_form,custom_blocks'
).split(',');

const MAX_ATTEMPTS = Number(process.env.FETCH_MAX_ATTEMPTS || 4);
const BASE_DELAY_MS = Number(process.env.FETCH_RETRY_BASE_MS || 2000);

function pathSlug(p) {
  return p.replace(/^\/|\/$/g, '').replace(/\//g, '_') || 'root';
}

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

async function fetchComparable(page, url) {
  for (let attempt = 1; attempt <= MAX_ATTEMPTS; attempt++) {
    try {
      const resp = await page.goto(url, { waitUntil: 'load', timeout: 120000 });
      await dismissOverlays(page);
      await page.waitForTimeout(900);
      await stabilizeParityViewport(page);
      const html = await page.content();
      const status = resp?.status() ?? 0;
      const captcha = /sgcaptcha|siteground|captcha/i.test(html) && html.length < 40000;
      const hasMain = html.includes('entry-content') || html.includes('wp-block-post-content');
      if (status === 200 && !captcha && hasMain) {
        return { ok: true, html, status, attempt };
      }
      if (attempt < MAX_ATTEMPTS) await sleep(BASE_DELAY_MS * 2 ** (attempt - 1));
    }
    catch (e) {
      if (attempt === MAX_ATTEMPTS) return { ok: false, error: String(e) };
      await sleep(BASE_DELAY_MS * 2 ** (attempt - 1));
    }
  }
  return { ok: false, reason: 'captcha_or_shell' };
}

if (!fs.existsSync(classificationPath)) {
  console.error('Run parity:article-variants:classify first');
  process.exit(1);
}

const classification = JSON.parse(fs.readFileSync(classificationPath, 'utf8'));
const pathToVariants = new Map();
for (const key of VARIANT_KEYS) {
  const entry = classification.samples?.[key.trim()];
  if (!entry?.paths) continue;
  for (const p of entry.paths) {
    if (!pathToVariants.has(p)) pathToVariants.set(p, []);
    pathToVariants.get(p).push(key.trim());
  }
}

fs.mkdirSync(outRoot, { recursive: true });

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await preparePage(page);

const manifest = { generatedAt: new Date().toISOString(), paths: {} };

for (const [p, variants] of pathToVariants) {
  const url = `${wpBase}${p.endsWith('/') ? p : `${p}/`}`;
  const slug = pathSlug(p);
  const result = await fetchComparable(page, url);
  const row = { path: p, variants, url, ...result };
  if (result.ok && result.html) {
    const htmlPath = path.join(outRoot, `${slug}.html`);
    fs.writeFileSync(htmlPath, result.html);
    row.htmlFile = `cache/wp-article-variants/${slug}.html`;
    delete row.html;
  }
  manifest.paths[p] = row;
}

await browser.close();
const manifestPath = path.join(outRoot, 'manifest.json');
fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));
console.log(JSON.stringify({ manifestPath, pathCount: pathToVariants.size, ok: Object.values(manifest.paths).filter((r) => r.ok).length }, null, 2));
