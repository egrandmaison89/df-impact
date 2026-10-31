#!/usr/bin/env node
/**
 * Text parity: all issue nodes vs live WordPress (desktop main content).
 * Skips paths containing "challenge" (non-comparable).
 */
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from '@playwright/test';
import { fileURLToPath } from 'node:url';
import { gotoWithBoundedSettle, preparePage } from '../lib/page-utils.mjs';
import {
  createIssueParityPage,
  issueLandingTextSimilarity,
} from '../lib/issue-playwright-text.mjs';
import { issueWpCacheOnly, resolveIssueWpHtml } from '../lib/issue-wp-baseline.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const manifest = JSON.parse(fs.readFileSync(path.join(parityRoot, 'manifest/site-manifest.json'), 'utf8'));
const drupalBase = process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888';
const wpBase = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');
const retries = Number(process.env.PARITY_LIVE_RETRIES || '2');
const minDelay = Number(process.env.PARITY_LIVE_DELAY_MS || '800');
const cacheOnly = issueWpCacheOnly();

function wpHtmlIsArchiveError(html) {
  return /arch_err|"post":"0"/.test(html) && !/ultp-post-grid-block/.test(html);
}

const issuePaths = (manifest.urls || [])
  .filter((e) => e.template === 'issue')
  .map((e) => e.path)
  .filter((p) => p && !/challenge/i.test(p));

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

function percentile(sorted, p) {
  if (!sorted.length) return null;
  const i = Math.min(sorted.length - 1, Math.max(0, Math.floor(p * (sorted.length - 1))));
  return sorted[i];
}

async function fetchWpHtml(page, url) {
  for (let attempt = 0; attempt <= retries; attempt++) {
    try {
      await gotoWithBoundedSettle(page, url, { timeout: 90000 });
      return await page.content();
    } catch (e) {
      if (attempt === retries) throw e;
      await sleep(minDelay * (attempt + 1));
    }
  }
  return '';
}

const browser = await chromium.launch();
const page = await createIssueParityPage(browser);

const results = [];
for (const p of issuePaths) {
  const wpUrl = `${wpBase}${p}${p.endsWith('/') ? '' : '/'}`;
  const drUrl = `${drupalBase}${p}`;
  let error = null;
  let sim = null;
  let resolved = { html: null, source: null };
  try {
    resolved = resolveIssueWpHtml(p);
    let wpHtml = resolved.html;
    if (wpHtml && wpHtmlIsArchiveError(wpHtml)) {
      results.push({ path: p, textSimilarity: null, error: 'wp_archive_error_non_comparable', skipped: true });
      continue;
    }
    if (!wpHtml) {
      if (cacheOnly) {
        results.push({
          path: p,
          textSimilarity: null,
          error: 'wp_cache_missing (set PARITY_ISSUE_WP_CACHE_ONLY=0 to allow live fetch)',
        });
        continue;
      }
      await fetchWpHtml(page, wpUrl);
      wpHtml = await page.content();
      if (wpHtmlIsArchiveError(wpHtml)) {
        results.push({ path: p, textSimilarity: null, error: 'wp_archive_error_non_comparable', skipped: true });
        continue;
      }
    }
    await sleep(minDelay);
    sim = await issueLandingTextSimilarity(page, { wpHtml, drupalUrl: drUrl });
  } catch (e) {
    error = String(e.message || e);
  }
  results.push({ path: p, textSimilarity: sim, error, wpSource: resolved?.source ?? (cacheOnly ? null : 'live') });
}

await browser.close();

const sims = results.map((r) => r.textSimilarity).filter((x) => typeof x === 'number').sort((a, b) => a - b);
const summary = {
  count: results.length,
  compared: sims.length,
  errors: results.filter((r) => r.error).length,
  textSimilarityMin: sims[0] ?? null,
  textSimilarityMedian: percentile(sims, 0.5),
  textSimilarityP10: percentile(sims, 0.1),
  textSimilarityP90: percentile(sims, 0.9),
  below98: results.filter((r) => typeof r.textSimilarity === 'number' && r.textSimilarity < 0.98).length,
};

const outDir = path.join(parityRoot, 'reports/progress/issue-landing-parity');
fs.mkdirSync(outDir, { recursive: true });
const outFile = path.join(outDir, `issue-live-sweep-${Date.now()}.json`);
fs.writeFileSync(outFile, JSON.stringify({ summary, results }, null, 2));
console.log(JSON.stringify({ outFile, summary }, null, 2));
