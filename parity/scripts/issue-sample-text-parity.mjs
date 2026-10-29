#!/usr/bin/env node
/** Text parity for fixed issue landing samples (ref pack desktop vs Drupal). */
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from '@playwright/test';
import { fileURLToPath } from 'node:url';
import { extractMainText, gotoWithBoundedSettle, preparePage, textSimilarity } from '../lib/page-utils.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const packRoot = path.join(path.resolve(__dirname, '..'), 'reference-pack');
const drupalBase = process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888';
const MIN_SIM = Number(process.env.PARITY_MIN_TEXT || '0.98');
const paths = (process.env.ISSUE_SAMPLE_PATHS || '/fall-2026-issue,/spring-2026-issue,/summer-2025-issue')
  .split(',')
  .map((p) => p.trim())
  .filter(Boolean);

function pathToPackSlug(p) {
  return p.replace(/^\/|\/$/g, '').replace(/\//g, '_');
}

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await preparePage(page);

const results = [];
for (const p of paths) {
  const refFile = path.join(packRoot, pathToPackSlug(p), 'desktop', 'rendered.html');
  if (!fs.existsSync(refFile)) {
    results.push({ path: p, error: 'missing ref pack', pass: false });
    continue;
  }
  const refHtml = fs.readFileSync(refFile, 'utf8');
  await page.setContent(refHtml, { waitUntil: 'domcontentloaded', timeout: 120000 });
  const refText = (await extractMainText(page)).text;
  await gotoWithBoundedSettle(page, `${drupalBase}${p}`);
  const drText = (await extractMainText(page)).text;
  const sim = textSimilarity(refText, drText);
  results.push({ path: p, textSimilarity: sim, pass: sim >= MIN_SIM });
}

await browser.close();

const failed = results.filter((r) => !r.pass);
console.log(JSON.stringify({ minSimilarity: MIN_SIM, results, pass: failed.length === 0 }, null, 2));
process.exit(failed.length ? 1 : 0);
