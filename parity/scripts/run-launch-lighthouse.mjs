#!/usr/bin/env node
/**
 * Lighthouse parity: homepage, issue, article, archive — mobile + desktop.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import lighthouse from 'lighthouse';
import * as chromeLauncher from 'chrome-launcher';
import { startRefPackServer } from './lib/ref-pack-server.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const outDir = path.join(parityRoot, 'reports', 'progress', 'launch-readiness');
const templates = JSON.parse(
  fs.readFileSync(path.join(parityRoot, 'config', 'launch-templates.json'), 'utf8'),
);
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const wpBase = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');
const useRefWp = process.env.LAUNCH_WP_SOURCE !== 'live';

function pickMetrics(lhr) {
  const a = lhr.audits;
  return {
    performance: lhr.categories.performance?.score ?? null,
    lcp: a['largest-contentful-paint']?.numericValue ?? null,
    cls: a['cumulative-layout-shift']?.numericValue ?? null,
    tbt: a['total-blocking-time']?.numericValue ?? null,
    weightKb: Math.round((a['total-byte-weight']?.numericValue ?? 0) / 1024),
    requests: a['network-requests']?.details?.items?.length ?? null,
  };
}

async function runOne(url, formFactor) {
  const chrome = await chromeLauncher.launch({ chromeFlags: ['--headless', '--no-sandbox'] });
  try {
    const options = {
      logLevel: 'error',
      output: 'json',
      onlyCategories: ['performance'],
      port: chrome.port,
      formFactor,
      screenEmulation:
        formFactor === 'mobile'
          ? { mobile: true, width: 412, height: 823, deviceScaleFactor: 2.625 }
          : { mobile: false, width: 1350, height: 940, deviceScaleFactor: 1 },
    };
    const result = await lighthouse(url, options);
    return pickMetrics(result.lhr);
  } finally {
    await chrome.kill();
  }
}

fs.mkdirSync(outDir, { recursive: true });
const refServer = useRefWp
  ? await startRefPackServer(path.join(parityRoot, 'reference-pack'))
  : null;

const rows = [];
for (const [key, cfg] of Object.entries(templates)) {
  const drUrl = `${drupalBase}${cfg.path.startsWith('/') ? cfg.path : `/${cfg.path}`}`;
  let wpUrl = `${wpBase}${cfg.path.startsWith('/') ? cfg.path : `/${cfg.path}`}`;
  if (refServer && cfg.refSlug) {
    wpUrl = `${refServer.baseUrl}/${cfg.refSlug}/desktop/rendered.html`;
  }
  for (const formFactor of ['mobile', 'desktop']) {
    const dr = await runOne(drUrl, formFactor);
    let wp = null;
    try {
      wp = await runOne(wpUrl, formFactor);
    } catch (e) {
      wp = { error: String(e) };
    }
    rows.push({ template: key, formFactor, drupal: dr, wordpress: wp, drUrl, wpUrl });
  }
}

if (refServer) {
  await refServer.close();
}

const outFile = path.join(outDir, `lighthouse-${Date.now()}.json`);
const report = { generatedAt: new Date().toISOString(), drupalBase, wpBase, useRefWp, rows };
fs.writeFileSync(outFile, JSON.stringify(report, null, 2));
console.log(JSON.stringify({ outFile, rowCount: rows.length }, null, 2));
