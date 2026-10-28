/**
 * Capture WordPress screenshots, main-content text, and meta into parity/baselines/<id>/.
 * Run from a network that is not blocked by SiteGround captcha (e.g. owner Mac on residential IP).
 * No cookies or credentials required.
 */
import { test } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import {
  dismissOverlays,
  extractMainText,
  extractMeta,
  preparePage,
  sampleComputedStyles,
  scrollForLazyLoad,
  waitForFonts,
} from '../lib/page-utils.mjs';
import { BREAKPOINTS, loadUrls, publicPath } from '../lib/manifest-urls.mjs';
import { baselineCaseDir, baselineMetaPath } from '../lib/wp-baseline.mjs';
import { resolveParityRoot } from '../lib/parity-root.mjs';

const parityRoot = resolveParityRoot();

const baselineId = process.env.PARITY_BASELINE_ID;
if (!baselineId) {
  throw new Error('Set PARITY_BASELINE_ID (e.g. df-impact-2026-10-28)');
}

const wpBase = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');
const baselineRoot = path.join(parityRoot, 'baselines', baselineId);

const STYLE_SELECTORS: Record<string, string> = {
  body: 'body',
  h1: 'h1',
  mainNav: 'header nav a, .menu--main a, nav.primary a',
  siteFooter: 'footer',
};

function wpUrl(pathNorm: string, entry: { url?: string }): string {
  if (entry.url) return entry.url;
  return `${wpBase}${pathNorm.startsWith('/') ? pathNorm : `/${pathNorm}`}`;
}

function writeJson(file: string, data: unknown) {
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, JSON.stringify(data, null, 2));
}

const urls = loadUrls();

test.describe.configure({ mode: 'serial' });

for (const entry of urls) {
  test(`capture ${entry.path}`, async ({ browser, browserName }) => {
    test.skip(browserName !== 'chromium', 'Baseline capture uses Chromium only');
    const pathNorm = publicPath(entry);
    const wpTarget = wpUrl(pathNorm, entry);

    for (const bp of BREAKPOINTS) {
      const page = await browser.newPage();
      await preparePage(page);
      await page.setViewportSize({ width: bp.width, height: bp.height });

      const outDir = baselineCaseDir(parityRoot, baselineId, pathNorm, bp.label);
      fs.mkdirSync(outDir, { recursive: true });

      const record: Record<string, unknown> = {
        path: pathNorm,
        template: entry.template,
        breakpoint: bp.label,
        wpUrl: wpTarget,
        capturedAt: new Date().toISOString(),
      };

      try {
        const resp = await page.goto(wpTarget, { waitUntil: 'domcontentloaded' });
        await dismissOverlays(page);
        await scrollForLazyLoad(page);
        await waitForFonts(page);
        await page.waitForTimeout(300);
        const meta = await extractMeta(page);
        record.status = resp?.status();
        record.meta = meta;
        record.content = await extractMainText(page);
        record.styles = await sampleComputedStyles(page, STYLE_SELECTORS);
        record.wpCaptcha =
          resp?.status() === 202 ||
          /checking the site connection|robot challenge/i.test(meta.title || '');
        await page.screenshot({ path: path.join(outDir, 'wordpress.png'), fullPage: true });
      } catch (e) {
        record.error = String(e);
      }

      writeJson(path.join(outDir, 'wordpress.json'), record);
      await page.close();
    }
  });
}

test.afterAll(async () => {
  writeJson(baselineMetaPath(parityRoot, baselineId), {
    baselineId,
    wpBase,
    capturedAt: new Date().toISOString(),
    urlCount: urls.length,
    sampleMode: process.env.PARITY_SAMPLE !== '0',
    manifestPath: path.join(parityRoot, 'manifest', 'site-manifest.json'),
    note: 'Frozen WordPress reference; comparison runs use PARITY_WP_BASELINE without hitting live WP.',
  });
});
