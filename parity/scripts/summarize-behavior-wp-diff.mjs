#!/usr/bin/env node
/**
 * Aggregate wp-drupal-behavior-diff.json files into a single report.
 *
 * Usage: node scripts/summarize-behavior-wp-diff.mjs [runId]
 */
import fs from 'node:fs';
import path from 'node:path';
import { resolveParityRoot } from '../lib/parity-root.mjs';

const parityRoot = resolveParityRoot();
const runId =
  process.argv[2] ||
  process.env.PARITY_BEHAVIOR_RUN_ID ||
  (() => {
    const dir = path.join(parityRoot, 'reports/behavior');
    const dirs = fs
      .readdirSync(dir)
      .filter((d) => fs.statSync(path.join(dir, d)).isDirectory())
      .sort()
      .reverse();
    return dirs[0];
  })();

const compareRoot = path.join(parityRoot, 'reports/behavior', runId, 'compare');
if (!fs.existsSync(compareRoot)) {
  console.error('No compare dir for run', runId);
  process.exit(1);
}

const cases = [];
for (const slug of fs.readdirSync(compareRoot)) {
  const diffFile = path.join(compareRoot, slug, 'wp-drupal-behavior-diff.json');
  if (!fs.existsSync(diffFile)) continue;
  cases.push(JSON.parse(fs.readFileSync(diffFile, 'utf8')));
}

const failing = cases.filter((c) => !c.match);
const bySection = {};
for (const c of failing) {
  for (const m of c.mismatches || []) {
    bySection[m.section] = (bySection[m.section] || 0) + 1;
  }
}

const byTemplate = {};
for (const c of cases) {
  const tpl = c.path || '/';
  if (!byTemplate[tpl]) {
    byTemplate[tpl] = { cases: 0, mismatchRows: 0, failingCases: 0 };
  }
  byTemplate[tpl].cases += 1;
  byTemplate[tpl].mismatchRows += c.mismatchCount || 0;
  if (!c.match) byTemplate[tpl].failingCases += 1;
}

const out = {
  runId,
  generatedAt: new Date().toISOString(),
  caseCount: cases.length,
  passCount: cases.filter((c) => c.match).length,
  failCount: failing.length,
  totalMismatchRows: cases.reduce((n, c) => n + (c.mismatchCount || 0), 0),
  passRate: cases.length ? (cases.length - failing.length) / cases.length : 0,
  failuresBySection: bySection,
  mismatchRowsByTemplate: Object.fromEntries(
    Object.entries(byTemplate).map(([k, v]) => [k, v.mismatchRows]),
  ),
  failures: failing.map((c) => ({
    path: c.path,
    breakpoint: c.breakpoint,
    mismatchCount: c.mismatchCount,
    mismatches: c.mismatches,
  })),
};

const outFile = path.join(parityRoot, 'reports/behavior', runId, 'wp-drupal-behavior-summary.json');
fs.writeFileSync(outFile, JSON.stringify(out, null, 2));
console.log(
  JSON.stringify(
    {
      outFile,
      runId,
      caseCount: out.caseCount,
      totalMismatchRows: out.totalMismatchRows,
      failCount: out.failCount,
      mismatchRowsByTemplate: out.mismatchRowsByTemplate,
    },
    null,
    2,
  ),
);

process.exit(out.totalMismatchRows > 0 ? 1 : 0);
