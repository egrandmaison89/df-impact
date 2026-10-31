#!/usr/bin/env node
/**
 * axe parity on launch templates (Drupal vs WP reference or live).
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
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

async function axeCount(page, context) {
  const results = await new AxeBuilder({ page, browserContext: context })
    .withTags(['wcag2a', 'wcag2aa', 'wcag21aa'])
    .analyze();
  return {
    violations: results.violations.length,
    violationIds: results.violations.map((v) => v.id),
    incomplete: results.incomplete.length,
  };
}

fs.mkdirSync(outDir, { recursive: true });
const refServer = useRefWp
  ? await startRefPackServer(path.join(parityRoot, 'reference-pack'))
  : null;

const browser = await chromium.launch();
const context = await browser.newContext();
const page = await context.newPage();

const rows = [];
for (const [key, cfg] of Object.entries(templates)) {
  const drUrl = `${drupalBase}${cfg.path}`;
  let wpUrl = `${wpBase}${cfg.path}`;
  if (refServer && cfg.refSlug) {
    wpUrl = `${refServer.baseUrl}/${cfg.refSlug}/desktop/rendered.html`;
  }
  await page.goto(drUrl, { waitUntil: 'load', timeout: 120000 });
  const dr = await axeCount(page, context);
  let wp = { skipped: true };
  try {
    await page.goto(wpUrl, { waitUntil: 'load', timeout: 120000 });
    wp = await axeCount(page, context);
  } catch (e) {
    wp = { error: String(e) };
  }
  const delta =
    typeof wp.violations === 'number' ? dr.violations - wp.violations : null;
  rows.push({ template: key, drupal: dr, wordpress: wp, deltaViolations: delta, drUrl, wpUrl });
}

await browser.close();
if (refServer) {
  await refServer.close();
}

const outFile = path.join(outDir, `axe-${Date.now()}.json`);
fs.writeFileSync(outFile, JSON.stringify({ generatedAt: new Date().toISOString(), rows }, null, 2));
const regressions = rows.filter((r) => r.deltaViolations !== null && r.deltaViolations > 0);
console.log(
  JSON.stringify({ outFile, regressions: regressions.length, rows }, null, 2),
);
if (regressions.length > 0) {
  process.exitCode = 1;
}
