#!/usr/bin/env node
/**
 * Capture Drupal vs live WP viewport screenshots for manual QA checklist items.
 */
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { dismissOverlays, gotoWithBoundedSettle } from '../lib/page-utils.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://df-impact.ddev.site').replace(/\/$/, '');
const wpBase = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');
const outRoot = path.join(parityRoot, 'reports/progress/mac-visual/qa-evidence');

const VIEWPORTS = [
  { label: '1440', width: 1440, height: 900 },
  { label: '768', width: 768, height: 900 },
  { label: '375', width: 375, height: 812 },
];

/** slug, drupal path, wp path (optional), optional scrollY, optional clip selector */
const CASES = [
  { id: '01-header-scroll', dr: '/homepage', wp: '/', scrollY: 120, clip: '.desktop-header, .impact-header' },
  { id: '02-translate-widget', dr: '/homepage', wp: '/', clip: '.gtranslate_wrapper, #gt-wrapper-23849622, .header' },
  { id: '03-vimeo-embed', dr: '/2024/06/the-charlie-landers-foundation', wp: '/2024/06/the-charlie-landers-foundation', clip: 'main' },
  { id: '04-pagination', dr: '/category/cancer-research/page/2', wp: '/category/cancer-research/page/2', clip: 'main' },
  { id: '05-in-brief-band', dr: '/fall-2026-issue', wp: '/fall-2026-issue', clip: '.wp-block-stackable-columns, .in-brief-button' },
  { id: '06-browse-issues-images', dr: '/browse-issues', wp: '/browse-issues', clip: 'main' },
  { id: '07-html-entities', dr: '/fall-2026-issue', wp: '/fall-2026-issue', clip: '.ultp-block-excerpt, .issue-page' },
  { id: '08-broken-images', dr: '/homepage', wp: '/', clip: 'main .ultp-block-image' },
  { id: '09-breadcrumb-edit', dr: '/2024/06/the-charlie-landers-foundation', wp: '/2024/06/the-charlie-landers-foundation', clip: 'main' },
  { id: '10-more-stories', dr: '/2024/06/the-charlie-landers-foundation', wp: '/2024/06/the-charlie-landers-foundation', clip: '.related-posts, .jp-relatedposts, main' },
  { id: '11-category-spacing', dr: '/2024/06/the-charlie-landers-foundation', wp: '/2024/06/the-charlie-landers-foundation', clip: 'main .wp-block-post-terms, .entry-footer, .taxonomy' },
];

async function shot(page, file, clipSel) {
  if (clipSel) {
    const loc = page.locator(clipSel).first();
    if ((await loc.count()) > 0) {
      await loc.screenshot({ path: file }).catch(async () => {
        await page.screenshot({ path: file, fullPage: false });
      });
      return;
    }
  }
  await page.screenshot({ path: file, fullPage: false });
}

async function captureSite(page, base, p, scrollY) {
  const url = `${base}${p.startsWith('/') ? p : `/${p}`}`;
  await gotoWithBoundedSettle(page, url, { timeout: 90000, settleMs: 1000 });
  if (scrollY) {
    await page.evaluate((y) => window.scrollTo(0, y), scrollY);
    await page.waitForTimeout(400);
  }
}

async function main() {
  fs.mkdirSync(outRoot, { recursive: true });
  const browser = await chromium.launch();
  const meta = { generatedAt: new Date().toISOString(), drupalBase, wpBase, cases: [] };

  for (const c of CASES) {
    const dir = path.join(outRoot, c.id);
    fs.mkdirSync(dir, { recursive: true });
    const caseMeta = { id: c.id, files: [] };

    for (const vp of VIEWPORTS) {
      for (const [site, base, pathKey] of [
        ['drupal', drupalBase, c.dr],
        ['wp', wpBase, c.wp || c.dr],
      ]) {
        const page = await browser.newPage();
        await page.setViewportSize({ width: vp.width, height: vp.height });
        await page.route('**/*', async (route) => {
          const u = route.request().url();
          if (/cookiebot|onetrust|quantcast/i.test(u)) return route.abort();
          return route.continue();
        });
        try {
          await captureSite(page, base, pathKey, c.scrollY);
          const rel = `${site}-${vp.label}.png`;
          const file = path.join(dir, rel);
          await shot(page, file, c.clip);
          caseMeta.files.push(`qa-evidence/${c.id}/${rel}`);
        } catch (e) {
          caseMeta.error = String(e);
        }
        await page.close();
      }
    }
    meta.cases.push(caseMeta);
  }

  fs.writeFileSync(path.join(outRoot, 'manifest.json'), JSON.stringify(meta, null, 2));
  console.log(JSON.stringify({ outRoot, caseCount: CASES.length }, null, 2));
  await browser.close();
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
