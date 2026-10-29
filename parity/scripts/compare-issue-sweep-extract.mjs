#!/usr/bin/env node
/** Compare content-sweep Cheerio extract vs PR #22-style text for one issue path. */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import * as cheerio from 'cheerio';
import { extractContentMetrics, wordJaccardSimilarity } from '../lib/content-sweep-extract.mjs';
import { loadIssueWpHtml } from '../lib/issue-wp-baseline.mjs';
import { wpExportHtmlForPath } from '../lib/wp-export-baseline.mjs';
import { textSimilarity, normalizeText } from '../lib/page-utils.mjs';
import { httpGet } from '../lib/http-get.mjs';

const issuePath = process.argv[2] || '/fall-2025-issue';
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');

const wpCache = loadIssueWpHtml(issuePath);
const wpExport = wpExportHtmlForPath(issuePath);
const dr = await httpGet(`${drupalBase}${issuePath}`, { timeoutMs: 45000 });

const wpSources = [
  ['issue-cache', wpCache],
  ['export-stub', wpExport],
].filter(([, html]) => html);

function cheerioMainText(html) {
  return extractContentMetrics(html).text;
}

function pr22StyleText(html) {
  const $ = cheerio.load(html);
  const main = $('main#main-content, main[role="main"], main').first();
  const text = main.text().replace(/\s+/g, ' ').trim();
  return normalizeText(text);
}

const drCheerio = cheerioMainText(dr.body);
const drPr22 = pr22StyleText(dr.body);

console.log(JSON.stringify({
  path: issuePath,
  drupalStatus: dr.status,
  drupalCheerioChars: drCheerio.length,
  drupalPr22Chars: drPr22.length,
  comparisons: wpSources.map(([name, html]) => {
    const wpCheerio = cheerioMainText(html);
    const wpPr22 = pr22StyleText(html);
    return {
      wpSource: name,
      wpCheerioChars: wpCheerio.length,
      wpPr22Chars: wpPr22.length,
      jaccard_cheerio: wordJaccardSimilarity(wpCheerio, drCheerio),
      pr22_textSimilarity: textSimilarity(wpPr22, drPr22),
      exportVsCache_jaccard: wpExport && wpCache ? wordJaccardSimilarity(cheerioMainText(wpExport), cheerioMainText(wpCache)) : null,
    };
  }),
}, null, 2));
