#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const runId = process.argv[2] || process.env.PARITY_RUN_ID;
const runDir = path.join(parityRoot, 'reports', 'runs', runId);
const resultsFile = path.join(runDir, 'results.json');
const outHtml = path.join(parityRoot, 'reports', 'latest-report.html');

if (!fs.existsSync(resultsFile)) {
  console.error(`Missing ${resultsFile}`);
  process.exit(1);
}

const { summary, results } = JSON.parse(fs.readFileSync(resultsFile, 'utf8'));

function rel(p) {
  return path.relative(path.dirname(outHtml), p).split(path.sep).join('/');
}

const rows = results
  .map((r) => {
    const slug = (r.path || '').replace(/^\//, '').replace(/[/?#]/g, '_') || 'root';
    const caseDir = path.join(runDir, slug, r.breakpoint || 'desktop');
    const wp = fs.existsSync(path.join(caseDir, 'wordpress.png'))
      ? rel(path.join(caseDir, 'wordpress.png'))
      : '';
    const dr = fs.existsSync(path.join(caseDir, 'drupal.png')) ? rel(path.join(caseDir, 'drupal.png')) : '';
    const diff = fs.existsSync(path.join(caseDir, 'diff.png')) ? rel(path.join(caseDir, 'diff.png')) : '';
    const px = typeof r.pixelDiffRatio === 'number' ? (r.pixelDiffRatio * 100).toFixed(2) + '%' : 'n/a';
    const txt = typeof r.textSimilarity === 'number' ? (r.textSimilarity * 100).toFixed(1) + '%' : 'n/a';
    return `<tr>
      <td><code>${escapeHtml(r.path)}</code></td>
      <td>${escapeHtml(r.template || '')}</td>
      <td>${escapeHtml(r.breakpoint || '')}</td>
      <td>${px}</td>
      <td>${txt}</td>
      <td>${r.urlParity?.wpOk ? 'ok' : 'fail'} / ${r.urlParity?.drupalOk ? 'ok' : 'fail'}</td>
      <td class="shots">${wp ? `<a href="${wp}"><img src="${wp}" alt="wp" loading="lazy"/></a>` : ''}
      ${dr ? `<a href="${dr}"><img src="${dr}" alt="drupal" loading="lazy"/></a>` : ''}
      ${diff ? `<a href="${diff}"><img src="${diff}" alt="diff" loading="lazy"/></a>` : ''}</td>
    </tr>`;
  })
  .join('\n');

const html = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <title>DF Impact parity report — ${escapeHtml(runId)}</title>
  <style>
    body { font-family: system-ui, sans-serif; margin: 1.5rem; color: #111; }
    table { border-collapse: collapse; width: 100%; font-size: 14px; }
    th, td { border: 1px solid #ccc; padding: 6px 8px; vertical-align: top; }
    th { background: #f4f4f4; text-align: left; }
    .shots img { max-height: 120px; margin-right: 4px; border: 1px solid #ddd; }
    pre { background: #f8f8f8; padding: 12px; overflow: auto; }
  </style>
</head>
<body>
  <h1>Dana-Farber Impact — WordPress vs Drupal parity</h1>
  <p>Run: <code>${escapeHtml(runId)}</code></p>
  <pre>${escapeHtml(JSON.stringify(summary, null, 2))}</pre>
  <table>
    <thead><tr>
      <th>Path</th><th>Template</th><th>Breakpoint</th><th>Pixel diff</th><th>Text sim</th><th>HTTP</th><th>Screenshots</th>
    </tr></thead>
    <tbody>${rows}</tbody>
  </table>
</body>
</html>`;

fs.writeFileSync(outHtml, html);
console.log(`Wrote ${outHtml}`);

function escapeHtml(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}
