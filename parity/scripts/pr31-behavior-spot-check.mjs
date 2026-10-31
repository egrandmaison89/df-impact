#!/usr/bin/env node
/** Hover/scroll spot-check for share, pager, header at 768 and 1024. */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';
import { gotoWithBoundedSettle, preparePage } from '../lib/page-utils.mjs';
import { captureSiteBehavior, DRUPAL_PROFILE, WP_PROFILE } from '../lib/behavior-profiles.mjs';
import { buildCrossSiteReport } from '../lib/behavior-compare.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const wpBase = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');
const outDir = path.join(parityRoot, 'reports/progress/pr31-visual');
fs.mkdirSync(outDir, { recursive: true });

const widths = [768, 1024];
const scenarios = [
  { id: 'article-share', drupal: '/2022/11/couple-commits-2-million-to-fund-groundbreaking-clinical-trials', wp: '/2022/11/couple-commits-2-million-to-fund-groundbreaking-clinical-trials' },
  { id: 'stories-pager', drupal: '/stories', wp: '/stories' },
  { id: 'issue-pager', drupal: '/fall-2025-issue', wp: '/fall-2025-issue' },
  { id: 'homepage', drupal: '/', wp: '/' },
];

const browser = await chromium.launch();
const rows = [];

for (const sc of scenarios) {
  for (const width of widths) {
    const bp = { label: width === 768 ? 'tablet' : 'tablet-wide', width, height: 900 };
    const wpPage = await browser.newPage();
    const drPage = await browser.newPage();
    await preparePage(wpPage);
    await preparePage(drPage);
    await wpPage.setViewportSize({ width, height: 900 });
    await drPage.setViewportSize({ width, height: 900 });
    const wpUrl = sc.wp === '/' ? wpBase : `${wpBase}${sc.wp}`;
    const drUrl = sc.drupal === '/' ? drupalBase : `${drupalBase}${sc.drupal}`;
    try {
      await gotoWithBoundedSettle(wpPage, wpUrl, { timeout: 240000, settleMs: 1500 });
      await gotoWithBoundedSettle(drPage, drUrl, { timeout: 120000, settleMs: 1500 });
      const wpSnap = await captureSiteBehavior(wpPage, WP_PROFILE, bp);
      const drSnap = await captureSiteBehavior(drPage, DRUPAL_PROFILE, bp);
      const cross = buildCrossSiteReport(sc.drupal, bp, wpSnap, drSnap);
      const pick = (snap, key) => snap?.[key] ?? null;
      rows.push({
        scenario: sc.id,
        width,
        headerScroll: {
          wp: pick(wpSnap, 'headerAfterScroll'),
          drupal: pick(drSnap, 'headerAfterScroll'),
        },
        shareHover: {
          wpDefault: pick(wpSnap, 'shareIconDefault'),
          wpHover: pick(wpSnap, 'shareIconHover'),
          drDefault: pick(drSnap, 'shareIconDefault'),
          drHover: pick(drSnap, 'shareIconHover'),
        },
        pagerHover: {
          wpDefault: pick(wpSnap, 'pagerDefault'),
          wpHover: pick(wpSnap, 'pagerHover'),
          drDefault: pick(drSnap, 'pagerDefault'),
          drHover: pick(drSnap, 'pagerHover'),
        },
        mismatchCount: cross?.mismatchCount ?? 0,
        mismatches: cross?.mismatches?.slice(0, 8) ?? [],
      });
    } catch (e) {
      rows.push({ scenario: sc.id, width, error: String(e) });
    }
    await wpPage.close();
    await drPage.close();
  }
}

await browser.close();
const outFile = path.join(outDir, 'behavior-spot-check.json');
fs.writeFileSync(outFile, JSON.stringify({ generatedAt: new Date().toISOString(), rows }, null, 2));
console.log(JSON.stringify({ outFile, rowCount: rows.length }, null, 2));
