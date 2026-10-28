#!/usr/bin/env node
/**
 * Exit 0 only when owner-approved thresholds pass (see docs/ACCEPTANCE_CRITERIA.md).
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  BREAKPOINT_LABELS,
  PIXEL_MEDIAN_MAX,
  PIXEL_P90_MAX,
  TEXT_SIMILARITY_MIN,
  metaMatches,
} from '../lib/thresholds.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const runId = process.argv[2] || process.env.PARITY_RUN_ID;
const resultsFile = path.join(parityRoot, 'reports', 'runs', runId, 'results.json');

if (!runId || !fs.existsSync(resultsFile)) {
  console.error('Usage: evaluate-thresholds.mjs <runId>');
  process.exit(2);
}

const { results } = JSON.parse(fs.readFileSync(resultsFile, 'utf8'));
const failures = [];

function groupKey(r) {
  return `${r.template || 'other'}::${r.breakpoint || 'unknown'}`;
}

const groups = {};
for (const r of results) {
  (groups[groupKey(r)] ||= []).push(r);
}

for (const [key, rows] of Object.entries(groups)) {
  const scoredRows = rows.filter((r) => !r.wpCaptcha);
  const pixels = scoredRows.map((r) => r.pixelDiffRatio).filter((x) => typeof x === 'number');
  const texts = scoredRows.map((r) => r.textSimilarity).filter((x) => typeof x === 'number');
  const med = median(pixels);
  const p90 = percentile(pixels, 0.9);
  const textMed = median(texts);

  if (med !== null && med > PIXEL_MEDIAN_MAX) {
    failures.push(`${key} pixel median ${(med * 100).toFixed(2)}% > ${PIXEL_MEDIAN_MAX * 100}%`);
  }
  if (p90 !== null && p90 > PIXEL_P90_MAX) {
    failures.push(`${key} pixel p90 ${(p90 * 100).toFixed(2)}% > ${PIXEL_P90_MAX * 100}%`);
  }
  if (textMed !== null && textMed < TEXT_SIMILARITY_MIN) {
    failures.push(`${key} text similarity ${(textMed * 100).toFixed(2)}% < ${TEXT_SIMILARITY_MIN * 100}%`);
  }
}

let urlTotal = 0;
let urlPass = 0;
let metaTotal = 0;
let metaPass = 0;

for (const r of results) {
  if (r.breakpoint !== 'desktop') continue;
  if (r.wpCaptcha) {
    failures.push(`WP captcha blocked ${r.path} — add cookies.txt for reliable text/meta/pixel`);
    continue;
  }
  urlTotal += 1;
  if (r.urlParity?.direct200 && r.urlParity?.samePath) urlPass += 1;
  else failures.push(`URL parity fail ${r.path}: ${JSON.stringify(r.urlParity)}`);

  if (r.wp?.meta && r.drupal?.meta) {
    metaTotal += 1;
    if (metaMatches(r.wp.meta, r.drupal.meta)) metaPass += 1;
    else failures.push(`Meta mismatch ${r.path}`);
  }
}

const report = {
  runId,
  evaluatedAt: new Date().toISOString(),
  thresholds: {
    pixelMedianMax: PIXEL_MEDIAN_MAX,
    pixelP90Max: PIXEL_P90_MAX,
    textSimilarityMin: TEXT_SIMILARITY_MIN,
  },
  urlParityPercent: urlTotal ? (urlPass / urlTotal) * 100 : 0,
  metaMatchPercent: metaTotal ? (metaPass / metaTotal) * 100 : 0,
  failureCount: failures.length,
  failures: failures.slice(0, 200),
};

const out = path.join(parityRoot, 'reports', 'runs', runId, 'evaluation.json');
fs.writeFileSync(out, JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));

process.exit(failures.length ? 1 : 0);

function median(arr) {
  if (!arr.length) return null;
  const s = [...arr].sort((a, b) => a - b);
  const m = Math.floor(s.length / 2);
  return s.length % 2 ? s[m] : (s[m - 1] + s[m]) / 2;
}

function percentile(arr, p) {
  if (!arr.length) return null;
  const s = [...arr].sort((a, b) => a - b);
  return s[Math.min(s.length - 1, Math.floor(p * s.length))];
}
