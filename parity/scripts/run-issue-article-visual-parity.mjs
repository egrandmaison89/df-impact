#!/usr/bin/env node
/**
 * Issue + article variant visual parity: frozen WP cache vs Drupal.
 * Pass-2 live WP HTML in parity/cache (not reference-pack totals).
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';
import { chromium } from '@playwright/test';
import { preparePage, dismissOverlays } from '../lib/page-utils.mjs';
import { stabilizeParityViewport } from '../lib/capture-stabilize.mjs';
import { loadWpFixtureHtml, pathSlug, prepareWpFixtureHtml, waitForWpFixtureReady } from '../lib/wp-fixture-html.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const wpBase = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');
const tag = process.env.VISUAL_TAG || 'before';
const runId = `${tag}-${Date.now()}`;

const issuePaths = (
  process.env.ISSUE_VISUAL_PATHS ||
  '/fall-2025-issue,/spring-2024-issue,/summer-2023-issue'
)
  .split(',')
  .map((s) => s.trim())
  .filter(Boolean);

const classification = JSON.parse(
  fs.readFileSync(
    path.join(parityRoot, 'reports/progress/article-variants-parity/variant-classification.json'),
    'utf8',
  ),
);
const variantKeys = (
  process.env.VARIANT_VISUAL_KEYS ||
  'hero,no_hero,sidebar,pull_quote,gallery,video_embed,long_form,custom_blocks'
).split(',')
  .map((s) => s.trim())
  .filter(Boolean);

const articleJobs = [];
for (const variant of variantKeys) {
  const sample = classification.samples?.[variant];
  const p = sample?.paths?.[0];
  if (!p) continue;
  articleJobs.push({ template: `article_${variant}`, path: p, variant });
}

const jobSpecs = [
  ...issuePaths.map((p) => `issue:${p}`),
  ...articleJobs.map((j) => `${j.template}:${j.path}`),
].join(',');

const styleRunDir = path.join(parityRoot, 'reports/computed-style-diff', runId);
const progressRoot = path.join(parityRoot, 'reports/progress/issue-article-visual', runId);
fs.mkdirSync(progressRoot, { recursive: true });

const styleEnv = {
  ...process.env,
  PARITY_WP_SOURCE: 'cache',
  PARITY_JOB_SPECS: jobSpecs,
  REPORT_RUN_ID: runId,
};
const styleProc = spawnSync('node', ['scripts/computed-style-diff.mjs'], {
  cwd: parityRoot,
  env: styleEnv,
  encoding: 'utf8',
});
process.stdout.write(styleProc.stdout || '');
process.stderr.write(styleProc.stderr || '');

const BPS = {
  desktop: { width: 1440, height: 900 },
  tablet: { width: 768, height: 1024 },
  mobile: { width: 375, height: 812 },
};

async function capturePair(pagePath, bp, outDir) {
  const loaded = loadWpFixtureHtml(pagePath);
  if (!loaded) {
    return { ok: false, error: 'missing_wp_fixture' };
  }
  fs.mkdirSync(outDir, { recursive: true });
  const vp = BPS[bp];
  const browser = await chromium.launch();
  const wpPage = await browser.newPage({ viewport: vp });
  const drPage = await browser.newPage({ viewport: vp });
  await preparePage(wpPage);
  await preparePage(drPage);
  await wpPage.setContent(prepareWpFixtureHtml(loaded.html, drupalBase), {
    waitUntil: 'load',
    timeout: 120000,
    url: `${wpBase}${pagePath}`,
  });
  await waitForWpFixtureReady(wpPage);
  await dismissOverlays(wpPage);
  await stabilizeParityViewport(wpPage);
  await drPage.goto(`${drupalBase}${pagePath}`, { waitUntil: 'load', timeout: 120000 });
  await dismissOverlays(drPage);
  await stabilizeParityViewport(drPage);
  await wpPage.waitForTimeout(600);
  await drPage.waitForTimeout(600);
  const wpOut = path.join(outDir, 'wp-viewport-mac.png');
  const drOut = path.join(outDir, 'drupal-viewport.png');
  await wpPage.screenshot({ path: wpOut, fullPage: false });
  await drPage.screenshot({ path: drOut, fullPage: false });
  await browser.close();
  return { ok: true, wpOut, drOut };
}

const regionReports = [];
for (const p of issuePaths) {
  for (const bp of Object.keys(BPS)) {
    const dir = path.join(progressRoot, `${pathSlug(p)}_${bp}`);
    const cap = await capturePair(p, bp, dir);
    if (!cap.ok) continue;
    const analyzeEnv = {
      ...process.env,
      ANALYZE_PATH: p,
      ANALYZE_BP: bp,
      ANALYZE_ID: `${pathSlug(p)}-${bp}-${tag}`,
      ANALYZE_PROGRESS_DIR: dir,
      PROGRESS_COMPONENT: 'issue-article-visual',
      DRUPAL_BASE_URL: drupalBase,
    };
    const proc = spawnSync('node', ['scripts/analyze-template-region-bands.mjs'], {
      cwd: parityRoot,
      env: analyzeEnv,
      encoding: 'utf8',
    });
    const reportPath = path.join(
      parityRoot,
      'reports/progress/mac-visual/qa-evidence',
      `${pathSlug(p)}-${bp}-${tag}`,
      'region-report.json',
    );
    if (fs.existsSync(reportPath)) {
      regionReports.push(JSON.parse(fs.readFileSync(reportPath, 'utf8')));
    }
  }
}

const agg = spawnSync('node', ['scripts/aggregate-style-mismatches.mjs', styleRunDir], {
  cwd: parityRoot,
  encoding: 'utf8',
});
const aggregate = JSON.parse(agg.stdout || '{}');

const manifest = {
  generatedAt: new Date().toISOString(),
  tag,
  runId,
  wpBaseline: 'parity/cache (pass-2 live WP HTML)',
  styleRunDir,
  styleSummary: fs.existsSync(path.join(styleRunDir, 'summary.json'))
    ? JSON.parse(fs.readFileSync(path.join(styleRunDir, 'summary.json'), 'utf8'))
    : null,
  aggregateTop: aggregate.top?.slice(0, 25) || [],
  issuePaths,
  articleJobs,
  regionReports: regionReports.map((r) => ({
    path: r.path,
    bp: r.breakpoint,
    overallDiffPercent: r.overallDiffPercent,
    regions: r.regions?.slice(0, 8),
    cascade: r.cascade,
  })),
};
const outFile = path.join(progressRoot, `visual-parity-${tag}.json`);
fs.writeFileSync(outFile, JSON.stringify(manifest, null, 2));
console.log(JSON.stringify({ outFile, styleRunDir, totalMismatches: manifest.styleSummary?.totalMismatches }, null, 2));
