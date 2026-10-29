import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { PNG } from 'pngjs';
import pixelmatch from 'pixelmatch';
import AxeBuilder from '@axe-core/playwright';
import {
  dismissOverlays,
  extractMainText,
  extractMeta,
  normalizeText,
  preparePage,
  sampleComputedStyles,
  scrollForLazyLoad,
  textSimilarity,
  waitForFonts,
} from '../lib/page-utils.mjs';
import { stabilizeParityViewport } from '../lib/capture-stabilize.mjs';
import { metaMatches } from '../lib/thresholds.mjs';
import {
  BREAKPOINTS,
  loadUrls,
  publicPath,
  repoRoot,
} from '../lib/manifest-urls.mjs';
import { copyBaselineScreenshot, loadBaselineCase } from '../lib/wp-baseline.mjs';

const parityRoot = path.join(repoRoot, 'parity');
const runId = process.env.PARITY_RUN_ID || new Date().toISOString().replace(/[:.]/g, '-');
const outDir = path.join(parityRoot, 'reports', 'runs', runId);

const wpBase = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const wpBaselineId = process.env.PARITY_WP_BASELINE || '';

const STYLE_SELECTORS: Record<string, string> = {
  body: 'body',
  h1: 'h1',
  mainNav: 'header nav a, .menu--main a, nav.primary a',
  siteFooter: 'footer',
};

type ManifestEntry = {
  path: string;
  url?: string;
  template?: string;
  drupal_alias?: string;
};

function wpUrl(entry: ManifestEntry): string {
  if (entry.url) return entry.url;
  const p = entry.path.startsWith('/') ? entry.path : `/${entry.path}`;
  return `${wpBase}${p}`;
}

function ensureDir(p: string) {
  fs.mkdirSync(p, { recursive: true });
}

function writeJson(file: string, data: unknown) {
  ensureDir(path.dirname(file));
  fs.writeFileSync(file, JSON.stringify(data, null, 2));
}

function diffPixels(a: Buffer, b: Buffer): { ratio: number; diff: Buffer; width: number; height: number } {
  const imgA = PNG.sync.read(a);
  const imgB = PNG.sync.read(b);
  const width = Math.min(imgA.width, imgB.width);
  const height = Math.min(imgA.height, imgB.height);
  const croppedA = new PNG({ width, height });
  const croppedB = new PNG({ width, height });
  PNG.bitblt(imgA, croppedA, 0, 0, width, height, 0, 0);
  PNG.bitblt(imgB, croppedB, 0, 0, width, height, 0, 0);
  const diff = new PNG({ width, height });
  const mismatched = pixelmatch(croppedA.data, croppedB.data, diff.data, width, height, {
    threshold: 0.1,
    includeAA: true,
  });
  return { ratio: mismatched / (width * height), diff: PNG.sync.write(diff), width, height };
}

const urls = loadUrls();

test.describe.configure({ mode: 'serial' });

for (const entry of urls) {
  test(`parity ${entry.path}`, async ({ browser, browserName, request }) => {
    test.skip(browserName !== 'chromium', 'Parity screenshots use Chromium only');

    for (const bp of BREAKPOINTS) {
      const useFrozenWp = Boolean(wpBaselineId);
      const drPage = await browser.newPage();
      await preparePage(drPage);
      await drPage.setViewportSize({ width: bp.width, height: bp.height });
      let wpPage = null;
      if (!useFrozenWp) {
        wpPage = await browser.newPage();
        await preparePage(wpPage);
        await wpPage.setViewportSize({ width: bp.width, height: bp.height });
      }

      const slug = entry.path.replace(/^\//, '').replace(/[/?#]/g, '_') || 'root';
      const caseDir = path.join(outDir, slug, bp.label);
      ensureDir(caseDir);

      const pathNorm = publicPath(entry);
      const wpTarget = wpUrl({ ...entry, path: pathNorm });
      const drupalTarget = `${drupalBase}${pathNorm}`;

      const result: Record<string, unknown> = {
        path: pathNorm,
        template: entry.template,
        breakpoint: bp.label,
        wpUrl: wpTarget,
        drupalUrl: drupalTarget,
        wpBaseline: useFrozenWp ? wpBaselineId : null,
        startedAt: new Date().toISOString(),
      };

      let wpOk = false;
      let drupalOk = false;
      let direct200 = false;

    try {
      if (useFrozenWp) {
        const frozen = loadBaselineCase(parityRoot, wpBaselineId, pathNorm, bp.label);
        if (!frozen) {
          result.wpError = `Missing baseline for ${pathNorm} (${bp.label})`;
        } else {
          copyBaselineScreenshot(frozen.pngPath, path.join(caseDir, 'wordpress.png'));
          result.wp = {
            status: frozen.wp.status,
            meta: frozen.wp.meta,
            content: frozen.wp.content,
            styles: frozen.wp.styles,
          };
          result.wpCaptcha = Boolean(frozen.wp.wpCaptcha);
          wpOk = !frozen.wp.error && typeof frozen.wp.status === 'number' && frozen.wp.status < 400;
        }
      } else {
        const wpResp = await wpPage!.goto(wpTarget, { waitUntil: 'domcontentloaded' });
        wpOk = !!wpResp && wpResp.status() < 400;
        await stabilizeParityViewport(wpPage!);
        result.wp = {
          status: wpResp?.status(),
          meta: await extractMeta(wpPage!),
          content: await extractMainText(wpPage!),
          styles: await sampleComputedStyles(wpPage!, STYLE_SELECTORS),
        };
        result.wpCaptcha =
          wpResp?.status() === 202 ||
          /checking the site connection|robot challenge/i.test(
            (result.wp as { meta?: { title?: string } }).meta?.title || '',
          );
        await wpPage!.screenshot({ path: path.join(caseDir, 'wordpress.png'), fullPage: true });
      }
    } catch (e) {
      result.wpError = String(e);
    }

    try {
      const dResp = await drPage.goto(drupalTarget, { waitUntil: 'domcontentloaded' });
      drupalOk = !!dResp && dResp.status() < 400;
      await stabilizeParityViewport(drPage);
      result.drupal = {
        status: dResp?.status(),
        meta: await extractMeta(drPage),
        content: await extractMainText(drPage),
        styles: await sampleComputedStyles(drPage, STYLE_SELECTORS),
      };
      await drPage.screenshot({ path: path.join(caseDir, 'drupal.png'), fullPage: true });
    } catch (e) {
      result.drupalError = String(e);
    }

    if (fs.existsSync(path.join(caseDir, 'wordpress.png')) && fs.existsSync(path.join(caseDir, 'drupal.png'))) {
      const wpPng = fs.readFileSync(path.join(caseDir, 'wordpress.png'));
      const drPng = fs.readFileSync(path.join(caseDir, 'drupal.png'));
      const { ratio, diff } = diffPixels(wpPng, drPng);
      fs.writeFileSync(path.join(caseDir, 'diff.png'), diff);
      result.pixelDiffRatio = ratio;
    }

    const wpText = (result.wp as { content?: { text?: string } })?.content?.text || '';
    const drText = (result.drupal as { content?: { text?: string } })?.content?.text || '';
    result.textSimilarity = textSimilarity(wpText, drText);

    const wpTitle = (result.wp as { meta?: { title?: string } })?.meta?.title || '';
    const drTitle = (result.drupal as { meta?: { title?: string } })?.meta?.title || '';
    result.titleMatch = normalizeText(wpTitle) === normalizeText(drTitle);

      try {
        const drDirect = await request.get(drupalTarget, { maxRedirects: 0, failOnStatusCode: false });
        direct200 = drDirect.status() === 200;
        const loc = drDirect.headers()['location'];
        result.urlParity = {
          wpOk,
          drupalOk,
          direct200,
          samePath: direct200 && !loc,
          drupalStatus: drDirect.status(),
          redirectLocation: loc || null,
        };
      } catch (e) {
        result.urlParity = { wpOk, drupalOk, direct200: false, error: String(e) };
      }

      const wpMeta = (result.wp as { meta?: Record<string, string> })?.meta;
      const drMeta = (result.drupal as { meta?: Record<string, string> })?.meta;
      result.metaMatch = wpMeta && drMeta ? metaMatches(wpMeta, drMeta) : false;

    if (process.env.PARITY_AXE === '1' && drupalOk) {
      await drPage.goto(drupalTarget, { waitUntil: 'domcontentloaded' });
      const axe = await new AxeBuilder({ page: drPage }).analyze();
      result.axe = {
        violations: axe.violations.length,
        incomplete: axe.incomplete.length,
      };
    }

    result.finishedAt = new Date().toISOString();
    writeJson(path.join(caseDir, 'result.json'), result);
      if (wpPage) await wpPage.close();
      await drPage.close();
    }
  });
}

test.afterAll(async () => {
  writeJson(path.join(outDir, 'manifest-meta.json'), {
    runId,
    wpBase,
    drupalBase,
    urlCount: urls.length,
    sampleMode: process.env.PARITY_SAMPLE !== '0',
    wpBaseline: wpBaselineId || null,
  });
});
