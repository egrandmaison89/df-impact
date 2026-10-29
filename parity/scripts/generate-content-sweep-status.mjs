#!/usr/bin/env node
/**
 * Build parity/reports/content-sweep/STATUS.md from latest sweep-*.json
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outDir = path.join(path.resolve(__dirname, '..'), 'reports', 'content-sweep');
const sweepFiles = fs
  .readdirSync(outDir)
  .filter((f) => f.startsWith('sweep-') && f.endsWith('.json'))
  .sort();
if (!sweepFiles.length) {
  console.error('No sweep JSON found');
  process.exit(1);
}
const latest = sweepFiles[sweepFiles.length - 1];
const report = JSON.parse(fs.readFileSync(path.join(outDir, latest), 'utf8'));

const clusterCounts = {};
for (const row of report.rows) {
  for (const c of row.clusters || ['unknown']) {
    clusterCounts[c] = (clusterCounts[c] || 0) + 1;
  }
}
const sortedClusters = Object.entries(clusterCounts).sort((a, b) => b[1] - a[1]);

const worst = [...report.rows]
  .filter((r) => r.wpComparable !== false && r.drupalStatus === 200)
  .sort((a, b) => (a.textSimilarity ?? 0) - (b.textSimilarity ?? 0))
  .slice(0, 50);

const buckets = [
  [0.98, 1.0, '≥ 0.98'],
  [0.95, 0.98, '0.95 – 0.98'],
  [0.9, 0.95, '0.90 – 0.95'],
  [0, 0.9, '< 0.90'],
];
const dist = buckets.map(([lo, hi, label]) => {
  const n = report.rows.filter((r) => {
    if (r.wpComparable === false || r.drupalStatus !== 200) return false;
    const sim = r.textSimilarity ?? 0;
    if (hi >= 1) return sim >= lo;
    return sim >= lo && sim < hi;
  }).length;
  return { label, count: n };
});

let md = `# Content sweep STATUS

Latest artifact: \`${latest}\` · CSV: \`${latest.replace('.json', '.csv')}\`

| Metric | Value |
| --- | --- |
| URLs checked | ${report.total} |
| WP non-comparable (challenge / fetch fail) | ${report.wpNonComparable} |
| Comparable (WP + Drupal 200) | ${report.comparable} |
| **Pass** (≥98% text, 0 shortcodes, 0 wp-internal links, links OK) | **${report.passed}** (${((report.passRateAmongComparable || 0) * 100).toFixed(1)}% of comparable) |
| ≥98% text similarity (comparable) | ${report.text98AmongComparable} |
| ≥98% text similarity (all ${report.total} URLs) | ${report.text98AmongAll ?? report.rows.filter((r) => (r.textSimilarity ?? 0) >= 0.98).length} |

## Text similarity distribution (comparable)

| Bucket | Count |
| --- | --- |
${dist.map((d) => `| ${d.label} | ${d.count} |`).join('\n')}

## Failure clusters (row may have multiple)

| Cluster | Rows |
| --- | --- |
${sortedClusters.map(([k, v]) => `| ${k} | ${v} |`).join('\n')}

## Worst 50 (by text similarity, comparable only)

| Path | Template | Similarity | Shortcodes | WP int links | Clusters |
| --- | --- | --- | --- | --- | --- |
${worst
  .map(
    (r) =>
      `| ${r.path} | ${r.template} | ${(r.textSimilarity ?? 0).toFixed(3)} | ${r.unrenderedShortcodes ?? 0} | ${r.wpInternalLinksInDrupal ?? 0} | ${(r.clusters || []).join(', ')} |`,
  )
  .join('\n')}

## Re-run

\`\`\`bash
cd parity
npm install
DRUPAL_BASE_URL=http://127.0.0.1:8888 npm run parity:content:sweep
node scripts/generate-content-sweep-status.mjs
\`\`\`
`;

fs.writeFileSync(path.join(outDir, 'STATUS.md'), md);
console.log(JSON.stringify({ statusFile: path.join(outDir, 'STATUS.md'), latest }, null, 2));
