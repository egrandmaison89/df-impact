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

const repoRoot = path.resolve(__dirname, '..', '..');
const manifestPath = path.join(repoRoot, 'parity', 'manifest', 'site-manifest.json');
const runId = process.env.PARITY_RUN_ID || new Date().toISOString().replace(/[:.]/g, '-');
const outDir = path.join(repoRoot, 'parity', 'reports', 'runs', runId);

const wpBase = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');

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

function loadUrls(): ManifestEntry[] {
  const raw = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
  let urls: ManifestEntry[] = raw.urls || [];
  const sample = process.env.PARITY_SAMPLE !== '0';
  const limit = parseInt(process.env.PARITY_LIMIT || '0', 10);

  if (sample) {
    const byTemplate: Record<string, ManifestEntry[]> = {};
    for (const u of urls) {
      const t = u.template || 'other';
      (byTemplate[t] ||= []).push(u);
    }
    const picked: ManifestEntry[] = [];
    const mustPaths = new Set(['/homepage/', '/home', '/issues', '/stories', '/in-brief']);
    for (const u of urls) {
      if (mustPaths.has(u.path) || mustPaths.has(u.path + '/')) picked.push(u);
    }
    for (const [tpl, list] of Object.entries(byTemplate)) {
      const sorted =
        tpl === 'article'
          ? [...list].sort((a, b) => (b.drupal_alias ? 1 : 0) - (a.drupal_alias ? 1 : 0))
          : list;
      const n = tpl === 'article' ? 8 : tpl === 'in_brief' ? 5 : tpl === 'issue' ? 14 : 3;
      picked.push(...sorted.slice(0, n));
    }
    const seen = new Set<string>();
    urls = picked.filter((u) => {
      if (seen.has(u.path)) return false;
      seen.add(u.path);
      return true;
    });
  }

  urls = urls.filter(
    (u) => u.drupal_alias || !/^\/\d{4}\//.test(u.path) || u.template === 'issue',
  );

  if (limit > 0) urls = urls.slice(0, limit);
  return urls;
}

function drupalPath(entry: ManifestEntry): string {
  const alias = entry.drupal_alias || entry.path;
  return alias.startsWith('/') ? alias : `/${alias}`;
}

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

const BREAKPOINTS = [
  { label: 'desktop', width: 1440, height: 900 },
  { label: 'tablet', width: 768, height: 1024 },
  { label: 'mobile', width: 375, height: 812 },
];

const urls = loadUrls();

test.describe.configure({ mode: 'serial' });

for (const entry of urls) {
  test(`parity ${entry.path}`, async ({ page, browserName }) => {
    test.skip(browserName !== 'chromium', 'Parity screenshots use Chromium only');
    await preparePage(page);

    for (const bp of BREAKPOINTS) {
      await page.setViewportSize({ width: bp.width, height: bp.height });

      const slug = entry.path.replace(/^\//, '').replace(/[/?#]/g, '_') || 'root';
      const caseDir = path.join(outDir, slug, bp.label);
      ensureDir(caseDir);

      const wpTarget = wpUrl(entry);
      const drupalTarget = `${drupalBase}${drupalPath(entry)}`;

      const result: Record<string, unknown> = {
      path: entry.path,
      template: entry.template,
      breakpoint: bp.label,
      wpUrl: wpTarget,
      drupalUrl: drupalTarget,
      startedAt: new Date().toISOString(),
    };

    let wpOk = false;
    let drupalOk = false;

    try {
      const wpResp = await page.goto(wpTarget, { waitUntil: 'domcontentloaded' });
      wpOk = !!wpResp && wpResp.status() < 400;
      await dismissOverlays(page);
      await scrollForLazyLoad(page);
      await waitForFonts(page);
      await page.waitForTimeout(300);
      result.wp = {
        status: wpResp?.status(),
        meta: await extractMeta(page),
        content: await extractMainText(page),
        styles: await sampleComputedStyles(page, STYLE_SELECTORS),
      };
      await page.screenshot({ path: path.join(caseDir, 'wordpress.png'), fullPage: true });
    } catch (e) {
      result.wpError = String(e);
    }

    try {
      const dResp = await page.goto(drupalTarget, { waitUntil: 'domcontentloaded' });
      drupalOk = !!dResp && dResp.status() < 400;
      await dismissOverlays(page);
      await scrollForLazyLoad(page);
      await waitForFonts(page);
      await page.waitForTimeout(300);
      result.drupal = {
        status: dResp?.status(),
        meta: await extractMeta(page),
        content: await extractMainText(page),
        styles: await sampleComputedStyles(page, STYLE_SELECTORS),
      };
      await page.screenshot({ path: path.join(caseDir, 'drupal.png'), fullPage: true });
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

    result.urlParity = {
      wpOk,
      drupalOk,
      samePath: entry.path === drupalPath(entry),
    };

    if (process.env.PARITY_AXE === '1' && drupalOk) {
      await page.goto(drupalTarget, { waitUntil: 'domcontentloaded' });
      const axe = await new AxeBuilder({ page }).analyze();
      result.axe = {
        violations: axe.violations.length,
        incomplete: axe.incomplete.length,
      };
    }

    result.finishedAt = new Date().toISOString();
    writeJson(path.join(caseDir, 'result.json'), result);
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
  });
});
