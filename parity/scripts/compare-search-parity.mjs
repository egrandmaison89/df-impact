#!/usr/bin/env node
/**
 * Structural + text parity: WP search fixtures vs Drupal /search?s=.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';
import { dismissOverlays, extractMainText, preparePage, textSimilarity } from '../lib/page-utils.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const fixtureRoot = path.join(parityRoot, 'cache', 'wp-search');
const outDir = path.join(parityRoot, 'reports', 'progress', 'article-variants-parity');
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const wpBase = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');
const queries = (process.env.QUERIES || 'cancer,jimmy,fund,zzznomatch99')
  .split(',')
  .map((q) => q.trim())
  .filter(Boolean);
const useLiveWp = process.env.PARITY_SEARCH_LIVE === '1';

fs.mkdirSync(outDir, { recursive: true });

async function metricsFromPage(page) {
  return page.evaluate(() => {
    const doc = document;
    const main = doc.querySelector('main') || doc.body;
    const cards = main.querySelectorAll(
      '.wp-block-post-template .wp-block-post, .search-results .card, .search-results .node--view-mode-teaser, .views-row',
    );
    const excerpts = [...main.querySelectorAll('.wp-block-post-excerpt__excerpt, .card__excerpt, .search-result__snippet')].map((el) =>
      el.textContent?.trim(),
    );
    const pager = doc.querySelector(
      '.wp-block-query-pagination, .pager, nav.pager, .pagination, .nav-links, ul.js-pager__items',
    );
    const h1 = doc.querySelector('.blue-border-header, .search-page-header h1, main h1');
    const empty = main.querySelector('.search-no-results, .search-no-results p, .no-results');
    return {
      title: doc.title,
      h1: h1?.textContent?.trim() || null,
      resultCards: cards.length,
      excerptCount: excerpts.filter(Boolean).length,
      hasPager: Boolean(pager),
      emptyText: empty?.textContent?.trim() || null,
      bodyClass: doc.body.className,
    };
  });
}

const browser = await chromium.launch();
const rows = [];

for (const q of queries) {
  const slug = q.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') || 'query';
  const fixture = path.join(fixtureRoot, `${slug}.html`);
  const row = { query: q, drupal: null, wp: null, textSimilarity: null, pass: false };

  const drPage = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  await preparePage(drPage);
  await drPage.goto(`${drupalBase}/search?s=${encodeURIComponent(q)}`, { waitUntil: 'load', timeout: 120000 });
  await dismissOverlays(drPage);
  await drPage.waitForTimeout(600);
  row.drupal = await metricsFromPage(drPage);
  const drText = (await extractMainText(drPage)).text;
  await drPage.close();

  const wpPage = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  await preparePage(wpPage);
  if (!useLiveWp && fs.existsSync(fixture)) {
    await wpPage.goto(`file://${fixture}`, { waitUntil: 'load' });
  }
  else {
    await wpPage.goto(`${wpBase}/?s=${encodeURIComponent(q)}`, { waitUntil: 'load', timeout: 120000 });
    await dismissOverlays(wpPage);
    await wpPage.waitForTimeout(900);
  }
  row.wp = await metricsFromPage(wpPage);
  const wpText = (await extractMainText(wpPage)).text;
  await wpPage.close();

  row.textSimilarity = textSimilarity(wpText, drText);
  const cardsMatch =
    row.wp.resultCards === row.drupal.resultCards ||
    (row.wp.resultCards >= 10 && row.drupal.resultCards >= 10);
  const h1Match = (row.wp.h1 || 'Search Results') === (row.drupal.h1 || 'Search Results');
  const pagerMatch = row.wp.hasPager === row.drupal.hasPager;
  const excerptOk = row.drupal.excerptCount >= Math.min(row.drupal.resultCards, 1);
  if (q.includes('nomatch') || q.startsWith('zzz')) {
    row.pass = row.drupal.resultCards === 0 && row.wp.resultCards === 0 && h1Match;
  }
  else {
    row.pass = cardsMatch && h1Match && pagerMatch && excerptOk;
  }
  rows.push(row);
}

await browser.close();

const report = { generatedAt: new Date().toISOString(), rows };
const outFile = path.join(outDir, `search-parity-${Date.now()}.json`);
fs.writeFileSync(outFile, JSON.stringify(report, null, 2));
console.log(JSON.stringify({ outFile, report }, null, 2));
