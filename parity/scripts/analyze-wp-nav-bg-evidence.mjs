#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import { resolveParityRoot } from '../lib/parity-root.mjs';

const dir = path.join(resolveParityRoot(), 'reports/behavior/wp-nav-bg-evidence');
const latest = path.join(dir, 'latest-summary.json');
const files = fs.existsSync(dir)
  ? fs.readdirSync(dir).filter((f) => f.endsWith('.json') && f !== 'latest-summary.json').sort()
  : [];
const dataFile = files.length ? path.join(dir, files[files.length - 1]) : null;
if (!dataFile) {
  console.error('No evidence JSON in', dir);
  process.exit(1);
}
const data = JSON.parse(fs.readFileSync(dataFile, 'utf8'));
const byPathBp = {};
for (const s of data.samples || []) {
  if (!s.nav?.found) continue;
  const k = `${s.path} @ ${s.breakpoint}`;
  if (!byPathBp[k]) byPathBp[k] = [];
  byPathBp[k].push(s.nav.backgroundColor);
}
const report = {
  source: dataFile,
  runsPerBreakpoint: data.runsPerBreakpoint,
  perPathBreakpoint: Object.fromEntries(
    Object.entries(byPathBp).map(([k, vals]) => {
      const uniq = [...new Set(vals)];
      return [k, { values: uniq, stable: uniq.length === 1, samples: vals.length }];
    }),
  ),
  globalStable: {
    tablet: [...new Set((data.samples || []).filter((s) => s.breakpoint === 'tablet' && s.nav?.found).map((s) => s.nav.backgroundColor))],
    mobile: [...new Set((data.samples || []).filter((s) => s.breakpoint === 'mobile' && s.nav?.found).map((s) => s.nav.backgroundColor))],
  },
  notFound: (data.samples || []).filter((s) => !s.nav?.found).length,
};
const out = path.join(dir, 'analysis.json');
fs.writeFileSync(out, JSON.stringify(report, null, 2));
console.log(JSON.stringify({ out, globalStable: report.globalStable, unstableCount: Object.values(report.perPathBreakpoint).filter((x) => !x.stable).length }, null, 2));
