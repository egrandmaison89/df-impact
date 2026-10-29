#!/usr/bin/env node
/** Sample text parity: random articles + issues from manifest (desktop, ref HTML vs Drupal). */
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from '@playwright/test';
import { fileURLToPath } from 'node:url';
import { extractMainText, gotoWithBoundedSettle, preparePage, textSimilarity } from '../lib/page-utils.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const packRoot = path.join(parityRoot, 'reference-pack');
const manifest = JSON.parse(fs.readFileSync(path.join(parityRoot, 'manifest/site-manifest.json'), 'utf8'));
const drupalBase = process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888';
const MIN_SIM = Number(process.env.PARITY_MIN_TEXT || '0.98');
const articleN = Number(process.env.PARITY_SAMPLE_ARTICLES || '20');
const issueN = Number(process.env.PARITY_SAMPLE_ISSUES || '5');
const seedRaw = process.env.PARITY_SAMPLE_SEED || '4185';
const seed = Number.parseInt(String(seedRaw).replace(/\D/g, ''), 10) || 4185;

function mulberry32(a) {
  return () => {
    a |= 0;
    a = (a + 0x6d2b79f5) | 0;
    let t = Math.imul(a ^ (a >>> 15), 1 | a);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

function pathToPackSlug(p) {
  if (p === '/digital-exclusives') return 'category_digital-exclusives';
  if (p === '/' || p === '') return 'root';
  if (p === '/homepage') return 'homepage';
  const m = p.match(/^\/(\d{4})\/(\d{2})\/(.+?)\/?$/);
  if (m) return `${m[1]}_${m[2]}_${m[3]}`;
  return p.replace(/^\/|\/$/g, '').replace(/\//g, '_');
}

function refHtmlPath(p) {
  const slug = pathToPackSlug(p);
  const file = path.join(packRoot, slug, 'desktop', 'rendered.html');
  return fs.existsSync(file) ? file : null;
}

const entries = manifest.urls || [];
const hasRef = (e) => refHtmlPath(e.path) !== null;
const articles = entries.filter((e) => e.template === 'article' && hasRef(e));
const issues = entries.filter((e) => e.template === 'issue' && hasRef(e));

const rng = mulberry32(seed);
function pick(arr, n) {
  const copy = [...arr];
  const out = [];
  while (out.length < n && copy.length) {
    const i = Math.floor(rng() * copy.length);
    out.push(copy.splice(i, 1)[0]);
  }
  return out;
}

const sampleArticles = pick(articles, Math.min(articleN, articles.length)).map((e) => e.path);
const sampleIssues = pick(issues, Math.min(issueN, issues.length)).map((e) => e.path);
const paths = [...sampleArticles, ...sampleIssues];
console.error(
  `Sample pool: ${articles.length} articles, ${issues.length} issues with ref pack; testing ${paths.length} paths`,
);

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await preparePage(page);

const results = [];
for (const p of paths) {
  const refFile = refHtmlPath(p);
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
const outDir = path.join(parityRoot, 'reports/progress/templates-inbrief-archives');
fs.mkdirSync(outDir, { recursive: true });
const outFile = path.join(outDir, `sample-text-parity-${Date.now()}.json`);
fs.writeFileSync(
  outFile,
  JSON.stringify(
    {
      minSimilarity: MIN_SIM,
      articles: articleN,
      issues: issueN,
      seed: seedRaw,
      pass: failed.length === 0,
      failed,
      results,
    },
    null,
    2,
  ),
);

console.log(JSON.stringify({ outFile, pass: failed.length === 0, failed: failed.length, min: Math.min(...results.map((r) => r.textSimilarity)) }, null, 2));
process.exit(failed.length ? 1 : 0);
