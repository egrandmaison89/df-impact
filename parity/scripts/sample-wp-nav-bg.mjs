#!/usr/bin/env node
/**
 * Sample live WP nav link computed background-color at tablet/mobile widths.
 * Usage: node scripts/sample-wp-nav-bg.mjs [runsPerBp=3]
 */
import { chromium } from '@playwright/test';
import { loadTemplateSamplePaths } from '../lib/behavior-paths.mjs';
import fs from 'node:fs';
import path from 'node:path';
import { resolveParityRoot } from '../lib/parity-root.mjs';

const wpBase = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');
const runsPerBp = Number(process.argv[2] || 3);
const navSelectors = {
  desktop: '#mega-menu-max_mega_menu_1 > li.mega-menu-item > a.mega-menu-link',
  mobile: '.mobile-header #mega-menu-max_mega_menu_1 > li.mega-menu-item > a.mega-menu-link, .mobile-header a.mega-menu-link',
};
const breakpoints = [
  { label: 'tablet', width: 768, height: 900 },
  { label: 'mobile', width: 375, height: 812 },
];
const paths = loadTemplateSamplePaths();

async function sampleNavBg(page, sel) {
  return page.evaluate((selector) => {
    const el = document.querySelector(selector);
    if (!el) return { found: false, backgroundColor: null, color: null };
    const cs = getComputedStyle(el);
    return {
      found: true,
      backgroundColor: cs.backgroundColor,
      color: cs.color,
    };
  }, sel);
}

const out = {
  generatedAt: new Date().toISOString(),
  wpBase,
  navSelectors,
  runsPerBreakpoint: runsPerBp,
  samples: [],
};

const browser = await chromium.launch();
for (const bp of breakpoints) {
  for (let run = 0; run < runsPerBp; run += 1) {
    for (const pathNorm of paths) {
      const url = pathNorm === '/homepage' ? `${wpBase}/` : `${wpBase}${pathNorm}`;
      const page = await browser.newPage();
      await page.setViewportSize({ width: bp.width, height: bp.height });
      let err = null;
      let nav = { found: false, backgroundColor: null };
      try {
        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 120_000 });
        await page.waitForTimeout(800);
        const sel = bp.label === 'mobile' ? navSelectors.mobile : navSelectors.desktop;
        nav = await sampleNavBg(page, sel);
        if (!nav.found && bp.label === 'mobile') {
          nav = await sampleNavBg(page, navSelectors.desktop);
        }
      } catch (e) {
        err = String(e);
      }
      out.samples.push({
        path: pathNorm,
        breakpoint: bp.label,
        run: run + 1,
        url,
        nav,
        error: err,
      });
      await page.close();
    }
  }
}
await browser.close();

const parityRoot = resolveParityRoot();
const outDir = path.join(parityRoot, 'reports/behavior', 'wp-nav-bg-evidence');
fs.mkdirSync(outDir, { recursive: true });
const stamp = new Date().toISOString().replace(/[:.]/g, '-');
const outFile = path.join(outDir, `${stamp}.json`);
fs.writeFileSync(outFile, JSON.stringify(out, null, 2));

const byKey = {};
for (const s of out.samples) {
  if (!s.nav?.found) continue;
  const k = `${s.path}@${s.breakpoint}`;
  if (!byKey[k]) byKey[k] = new Set();
  byKey[k].add(s.nav.backgroundColor);
}

const unstable = Object.entries(byKey).filter(([, set]) => set.size > 1);
const summary = {
  outFile,
  pathBreakpointKeys: Object.keys(byKey).length,
  unstableAcrossRuns: unstable.map(([k, set]) => ({ key: k, values: [...set] })),
  distinctBgByBreakpoint: {
    tablet: [...new Set(out.samples.filter((s) => s.breakpoint === 'tablet' && s.nav?.found).map((s) => s.nav.backgroundColor))],
    mobile: [...new Set(out.samples.filter((s) => s.breakpoint === 'mobile' && s.nav?.found).map((s) => s.nav.backgroundColor))],
  },
};

fs.writeFileSync(path.join(outDir, 'latest-summary.json'), JSON.stringify(summary, null, 2));
console.log(JSON.stringify(summary, null, 2));
