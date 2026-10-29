#!/usr/bin/env node
/** Summarize top style/rect mismatches from a computed-style-diff run directory. */
import fs from 'node:fs';
import path from 'node:path';

const runDir = process.argv[2];
if (!runDir || !fs.existsSync(runDir)) {
  console.error('Usage: node aggregate-style-mismatches.mjs <computed-style-run-dir>');
  process.exit(1);
}

const files = fs.readdirSync(runDir).filter((f) => f.endsWith('.json') && f !== 'summary.json');
const byKey = {};
let total = 0;

for (const file of files) {
  const report = JSON.parse(fs.readFileSync(path.join(runDir, file), 'utf8'));
  if (!report.mismatches) continue;
  total += report.mismatches.length;
  const label = `${report.template || '?'}:${report.path || '?'}:${report.bp || '?'}`;
  for (const m of report.mismatches) {
    const key =
      m.kind === 'style'
        ? `${m.selector} :: ${m.key}`
        : m.kind === 'rect'
          ? `${m.selector} :: rect.${m.dim}`
          : `${m.selector} :: ${m.kind}`;
    if (!byKey[key]) {
      byKey[key] = { key, count: 0, samples: [], kinds: new Set() };
    }
    byKey[key].count += 1;
    byKey[key].kinds.add(m.kind);
    if (byKey[key].samples.length < 3) {
      byKey[key].samples.push({
        job: label,
        wp: m.wp,
        drupal: m.drupal,
        delta: m.delta,
      });
    }
  }
}

const top = Object.values(byKey)
  .sort((a, b) => b.count - a.count)
  .slice(0, 40)
  .map((row) => ({
    ...row,
    kinds: [...row.kinds],
  }));

console.log(
  JSON.stringify(
    {
      runDir,
      fileCount: files.length,
      totalMismatches: total,
      top,
    },
    null,
    2,
  ),
);
