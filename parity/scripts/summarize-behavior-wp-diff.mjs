#!/usr/bin/env node
/**
 * Aggregate wp-drupal-behavior-diff.json files into a single report.
 *
 * Usage: node scripts/summarize-behavior-wp-diff.mjs [runId]
 */
import fs from 'node:fs';
import path from 'node:path';
import { expectedBehaviorCaseCount } from '../lib/behavior-paths.mjs';
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

const runDir = path.join(parityRoot, 'reports/behavior', runId);
const compareRoot = path.join(runDir, 'compare');
if (!fs.existsSync(compareRoot)) {
  console.error('No compare dir for run', runId);
  process.exit(1);
}

const metaPath = path.join(runDir, 'run-meta.json');
const meta = fs.existsSync(metaPath) ? JSON.parse(fs.readFileSync(metaPath, 'utf8')) : {};
const expectedCases =
  meta.expectedCases ??
  (Array.isArray(meta.paths) ? expectedBehaviorCaseCount(meta.paths) : null);

const cases = [];
const incomplete = [];

for (const slug of fs.readdirSync(compareRoot)) {
  const caseDir = path.join(compareRoot, slug);
  const diffFile = path.join(caseDir, 'wp-drupal-behavior-diff.json');
  const behaviorFile = path.join(caseDir, 'behavior.json');
  if (fs.existsSync(diffFile)) {
    cases.push(JSON.parse(fs.readFileSync(diffFile, 'utf8')));
    continue;
  }
  if (fs.existsSync(behaviorFile)) {
    const b = JSON.parse(fs.readFileSync(behaviorFile, 'utf8'));
    incomplete.push({
      slug,
      path: b.path,
      breakpoint: b.breakpoint,
      reason: b.captured === false ? 'cross.wp.snapshot' : 'missing_diff',
      wpError: b.wpError,
    });
  }
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
  expectedCases,
  caseCount: cases.length,
  capturedCaseCount: cases.length,
  incompleteCaseCount: incomplete.length,
  passCount: cases.filter((c) => c.match).length,
  failCount: failing.length,
  totalMismatchRows: cases.reduce((n, c) => n + (c.mismatchCount || 0), 0),
  passRate: cases.length ? (cases.length - failing.length) / cases.length : 0,
  failuresBySection: bySection,
  mismatchRowsByTemplate: Object.fromEntries(
    Object.entries(byTemplate).map(([k, v]) => [k, v.mismatchRows]),
  ),
  incompleteCases: incomplete,
  failures: failing.map((c) => ({
    path: c.path,
    breakpoint: c.breakpoint,
    mismatchCount: c.mismatchCount,
    mismatches: c.mismatches,
  })),
};

const outFile = path.join(runDir, 'wp-drupal-behavior-summary.json');
fs.writeFileSync(outFile, JSON.stringify(out, null, 2));
console.log(
  JSON.stringify(
    {
      outFile,
      runId,
      expectedCases: out.expectedCases,
      caseCount: out.caseCount,
      incompleteCaseCount: out.incompleteCaseCount,
      totalMismatchRows: out.totalMismatchRows,
      failCount: out.failCount,
      mismatchRowsByTemplate: out.mismatchRowsByTemplate,
    },
    null,
    2,
  ),
);

const incompleteGate =
  expectedCases != null && (out.caseCount + out.incompleteCaseCount < expectedCases || out.incompleteCaseCount > 0);
process.exit(out.totalMismatchRows > 0 || incompleteGate ? 1 : 0);
