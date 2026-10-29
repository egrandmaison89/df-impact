#!/usr/bin/env node
/**
 * Text + computed-style scores for article variant samples (2 per variant).
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';
import { chromium } from '@playwright/test';
import { dismissOverlays, extractMainText, preparePage, textSimilarity } from '../lib/page-utils.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const classificationPath = path.join(
  parityRoot,
  'reports/progress/article-variants-parity/variant-classification.json',
);
const outDir = path.join(parityRoot, 'reports/progress/article-variants-parity');
const wpBase = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const minText = Number(process.env.PARITY_MIN_TEXT || '0.98');

if (!fs.existsSync(classificationPath)) {
  console.error('Missing variant-classification.json — run parity:article-variants:classify');
  process.exit(1);
}

const classification = JSON.parse(fs.readFileSync(classificationPath, 'utf8'));
const pathSet = new Set();
for (const entry of Object.values(classification.samples || {})) {
  for (const p of entry.paths || []) {
    pathSet.add(p);
  }
}
const paths = [...pathSet];
fs.mkdirSync(outDir, { recursive: true });

const browser = await chromium.launch();
const textRows = [];

for (const p of paths) {
  const row = { path: p, text: null, pass: false };
  let wpText = '';
  let drText = '';
  for (const [label, base] of [
    ['wp', wpBase],
    ['drupal', drupalBase],
  ]) {
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await preparePage(page);
    try {
      await page.goto(`${base}${p}`, { waitUntil: 'load', timeout: 120000 });
      await dismissOverlays(page);
      await page.waitForTimeout(800);
      const t = (await extractMainText(page)).text;
      if (label === 'wp') wpText = t;
      else drText = t;
    }
    catch (e) {
      row.error = String(e);
    }
    await page.close();
  }
  row.text = textSimilarity(wpText, drText);
  row.pass = row.text >= minText;
  textRows.push(row);
}

await browser.close();

const styleRunId = `variant-${Date.now()}`;
const styleEnv = {
  ...process.env,
  PACK_COMPARE_PATHS: paths.join(','),
  PROGRESS_COMPONENT: 'article-variants-parity',
  REPORT_RUN_ID: styleRunId,
  PARITY_BP: 'desktop',
};
spawnSync('node', ['scripts/computed-style-diff.mjs'], { cwd: parityRoot, env: styleEnv, stdio: 'inherit' });

const styleSummaryPath = path.join(parityRoot, 'reports/computed-style-diff', styleRunId, 'summary.json');
let styleByPath = {};
if (fs.existsSync(styleSummaryPath)) {
  const styleSummary = JSON.parse(fs.readFileSync(styleSummaryPath, 'utf8'));
  for (const item of styleSummary.results || []) {
    styleByPath[item.path] = item;
  }
}

const table = [];
for (const [variant, meta] of Object.entries(classification.samples || {})) {
  for (const p of meta.paths || []) {
    const textRow = textRows.find((r) => r.path === p) || {};
    const styleRow = styleByPath[p] || {};
    table.push({
      variant,
      path: p,
      textScore: textRow.text,
      textPass: textRow.pass,
      stylePass: styleRow.pass ?? null,
      styleScore: styleRow.score ?? null,
      pixelPass: styleRow.pixelPass ?? null,
    });
  }
}

const report = {
  generatedAt: new Date().toISOString(),
  paths: paths.length,
  minText,
  table,
  textRows,
  styleRunId,
};
const outFile = path.join(outDir, `variant-samples-${Date.now()}.json`);
fs.writeFileSync(outFile, JSON.stringify(report, null, 2));
console.log(JSON.stringify({ outFile, pass: table.filter((t) => t.textPass).length, total: table.length }, null, 2));
