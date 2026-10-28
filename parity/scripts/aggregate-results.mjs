#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const runId = process.argv[2] || process.env.PARITY_RUN_ID;
if (!runId) {
  console.error('Usage: aggregate-results.mjs <runId>');
  process.exit(1);
}

const runDir = path.join(parityRoot, 'reports', 'runs', runId);
const outFile = path.join(parityRoot, 'reports', 'runs', runId, 'results.json');

function walk(dir, acc = []) {
  for (const ent of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, ent.name);
    if (ent.isDirectory()) walk(p, acc);
    else if (ent.name === 'result.json') acc.push(p);
  }
  return acc;
}

const files = fs.existsSync(runDir) ? walk(runDir) : [];
const results = files.map((f) => JSON.parse(fs.readFileSync(f, 'utf8')));

const summary = {
  runId,
  generatedAt: new Date().toISOString(),
  caseCount: results.length,
  byBreakpoint: {},
  headline: {},
};

const bpGroups = {};
for (const r of results) {
  const bp = r.breakpoint || 'unknown';
  (bpGroups[bp] ||= []).push(r);
}

for (const [bp, list] of Object.entries(bpGroups)) {
  const pixels = list.map((r) => r.pixelDiffRatio).filter((x) => typeof x === 'number');
  const text = list.map((r) => r.textSimilarity).filter((x) => typeof x === 'number');
  const wpOk = list.filter((r) => r.urlParity?.wpOk).length;
  const drOk = list.filter((r) => r.urlParity?.drupalOk).length;
  summary.byBreakpoint[bp] = {
    cases: list.length,
    wpReachable: wpOk,
    drupalReachable: drOk,
    pixelDiffMedian: median(pixels),
    pixelDiffP90: percentile(pixels, 0.9),
    textSimilarityMedian: median(text),
    textSimilarityP10: percentile(text, 0.1),
  };
}

summary.headline = {
  pixelDiffMedianDesktop: summary.byBreakpoint.desktop?.pixelDiffMedian ?? null,
  textSimilarityMedianDesktop: summary.byBreakpoint.desktop?.textSimilarityMedian ?? null,
  drupalReachableDesktop: summary.byBreakpoint.desktop?.drupalReachable ?? 0,
  wpReachableDesktop: summary.byBreakpoint.desktop?.wpReachable ?? 0,
};

const tplBpGroups = {};
for (const r of results) {
  const key = `${r.template || 'other'}::${r.breakpoint || 'unknown'}`;
  (tplBpGroups[key] ||= []).push(r);
}
summary.byTemplateBreakpoint = {};
for (const [key, list] of Object.entries(tplBpGroups)) {
  const pixels = list.map((r) => r.pixelDiffRatio).filter((x) => typeof x === 'number');
  const text = list.map((r) => r.textSimilarity).filter((x) => typeof x === 'number');
  summary.byTemplateBreakpoint[key] = {
    cases: list.length,
    pixelDiffMedian: median(pixels),
    pixelDiffP90: percentile(pixels, 0.9),
    textSimilarityMedian: median(text),
  };
}

fs.mkdirSync(path.dirname(outFile), { recursive: true });
fs.writeFileSync(outFile, JSON.stringify({ summary, results }, null, 2));
console.log(`Wrote ${outFile}`);

function median(arr) {
  if (!arr.length) return null;
  const s = [...arr].sort((a, b) => a - b);
  const m = Math.floor(s.length / 2);
  return s.length % 2 ? s[m] : (s[m - 1] + s[m]) / 2;
}

function percentile(arr, p) {
  if (!arr.length) return null;
  const s = [...arr].sort((a, b) => a - b);
  const idx = Math.min(s.length - 1, Math.floor(p * s.length));
  return s[idx];
}
