#!/usr/bin/env node
/** Quick text similarity debug for ref pack vs Drupal (desktop). */
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from '@playwright/test';
import { fileURLToPath } from 'node:url';
import { extractMainText, gotoWithBoundedSettle, preparePage, textSimilarity, normalizeText } from '../lib/page-utils.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const packRoot = path.join(parityRoot, 'reference-pack');
const drupalBase = process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888';

const paths = (process.env.PACK_COMPARE_PATHS || '/in-brief,/browse-issues,/category/digital-exclusives')
  .split(',')
  .map((p) => p.trim())
  .filter(Boolean);

function pathToPackSlug(p) {
  if (p === '/digital-exclusives') return 'category_digital-exclusives';
  if (p === '/' || p === '') return 'root';
  if (p === '/homepage') return 'homepage';
  const m = p.match(/^\/(\d{4})\/(\d{2})\/(.+?)\/?$/);
  if (m) return `${m[1]}_${m[2]}_${m[3]}`;
  return p.replace(/^\/|\/$/g, '').replace(/\//g, '_');
}

function refHtmlPath(p) {
  return path.join(packRoot, pathToPackSlug(p), 'desktop', 'rendered.html');
}

function wordDiff(ref, dr) {
  const wa = new Set(normalizeText(ref).split(' '));
  const wb = new Set(normalizeText(dr).split(' '));
  const onlyRef = [...wa].filter((w) => !wb.has(w)).slice(0, 40);
  const onlyDr = [...wb].filter((w) => !wa.has(w)).slice(0, 40);
  return { onlyRef, onlyDr, refWords: wa.size, drWords: wb.size };
}

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await preparePage(page);

for (const p of paths) {
  const refFile = refHtmlPath(p);
  const refHtml = fs.readFileSync(refFile, 'utf8');
  await page.setContent(refHtml, { waitUntil: 'domcontentloaded', timeout: 120000 });
  const refText = (await extractMainText(page)).text;
  await gotoWithBoundedSettle(page, `${drupalBase}${p}`);
  const drText = (await extractMainText(page)).text;
  const sim = textSimilarity(refText, drText);
  const diff = wordDiff(refText, drText);
  console.log('\n===', p, 'sim', sim.toFixed(4), '===');
  console.log('ref len', refText.length, 'drupal len', drText.length);
  console.log('only in ref (sample):', diff.onlyRef.join(', '));
  console.log('only in drupal (sample):', diff.onlyDr.join(', '));
}

await browser.close();
