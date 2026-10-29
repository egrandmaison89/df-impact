#!/usr/bin/env node
/**
 * Apples-to-apples: compare desktop runs (selector set, paths, WP comparability).
 *   node scripts/analyze-cs-baseline-drift.mjs post-merge-pr11 full-cs-2026-10-29
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');

const baselineRun = process.argv[2] || 'post-merge-pr11';
const currentRun = process.argv[3] || 'full-cs-2026-10-29';

function loadDesktop(runId) {
  const dir = path.join(parityRoot, 'reports', 'computed-style-diff', runId);
  const out = [];
  if (!fs.existsSync(dir)) return out;
  for (const f of fs.readdirSync(dir)) {
    if (!f.endsWith('.json') || f === 'summary.json' || f === 'aggregate.json') continue;
    const r = JSON.parse(fs.readFileSync(path.join(dir, f), 'utf8'));
    if (r.bp !== 'desktop' || !r.template) continue;
    out.push(r);
  }
  return out;
}

function key(r) {
  return `${r.template}|${r.path}`;
}

function isComparable(r) {
  return r.wpComparable === true || r.wpStatus === 200;
}

function sumByTemplate(rows, filter) {
  const sums = {};
  for (const r of rows) {
    if (filter && !filter(r)) continue;
    sums[r.template] = (sums[r.template] || 0) + (r.mismatchCount || 0);
  }
  return sums;
}

function selectorConfigFingerprint() {
  const p = path.join(parityRoot, 'config', 'parity-selectors.json');
  const j = JSON.parse(fs.readFileSync(p, 'utf8'));
  const all = [...j.chrome, ...j.content];
  return { chrome: j.chrome.length, content: j.content.length, total: all.length, selectors: all };
}

const fp = selectorConfigFingerprint();
const base = loadDesktop(baselineRun);
const cur = loadDesktop(currentRun);
const baseMap = Object.fromEntries(base.map((r) => [key(r), r]));
const curMap = Object.fromEntries(cur.map((r) => [key(r), r]));

const overlap = Object.keys(baseMap).filter((k) => curMap[k]);
const baseOnly = Object.keys(baseMap).filter((k) => !curMap[k]);
const curOnly = Object.keys(curMap).filter((k) => !baseMap[k]);

const report = {
  generatedAt: new Date().toISOString(),
  baselineRun,
  currentRun,
  selectorFingerprint: { total: fp.total, chrome: fp.chrome, content: fp.content },
  samplePaths: {
    baselineDesktopJobs: base.length,
    currentDesktopJobs: cur.length,
    overlap: overlap.length,
    baselineOnlyPaths: baseOnly,
    currentOnlyPaths: curOnly,
  },
  desktopMismatchSum: {
    baseline_all: sumByTemplate(base),
    current_all: sumByTemplate(cur),
    baseline_comparableOnly: sumByTemplate(base, isComparable),
    current_comparableOnly: sumByTemplate(cur, isComparable),
    overlap_baseline_all: overlap.reduce((s, k) => s + (baseMap[k].mismatchCount || 0), 0),
    overlap_current_all: overlap.reduce((s, k) => s + (curMap[k].mismatchCount || 0), 0),
    overlap_baseline_comparable: overlap.reduce(
      (s, k) => s + (isComparable(baseMap[k]) ? baseMap[k].mismatchCount || 0 : 0),
      0,
    ),
    overlap_current_comparable: overlap.reduce(
      (s, k) => s + (isComparable(curMap[k]) ? curMap[k].mismatchCount || 0 : 0),
      0,
    ),
  },
  perPathDelta: overlap
    .map((k) => {
      const a = baseMap[k];
      const b = curMap[k];
      return {
        key: k,
        baseline: { m: a.mismatchCount, wp: a.wpStatus, comparable: isComparable(a) },
        current: { m: b.mismatchCount, wp: b.wpStatus, comparable: isComparable(b) },
        delta: (b.mismatchCount || 0) - (a.mismatchCount || 0),
      };
    })
    .filter((row) => row.delta !== 0 || row.baseline.wp !== row.current.wp)
    .sort((x, y) => Math.abs(y.delta) - Math.abs(x.delta)),
  interpretation: [
    'Selector set unchanged if fingerprint.total matches prior docs (41 selectors).',
    'Baseline post-merge-pr11 often used wpStatus 0/202 → artificially low mismatch counts (missing WP DOM).',
    'Compare overlap_*_comparable and per-path wp status before calling regressions.',
  ],
};

const outFile = path.join(
  parityRoot,
  'reports',
  'computed-style-diff',
  `drift-${baselineRun}-vs-${currentRun}.json`,
);
fs.mkdirSync(path.dirname(outFile), { recursive: true });
fs.writeFileSync(outFile, JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
