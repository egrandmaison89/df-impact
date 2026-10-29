#!/usr/bin/env node
/** Side-by-side WP ref-pack vs Drupal computed styles for chrome selectors. */
import { chromium } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { startRefPackServer } from './lib/ref-pack-server.mjs';
import { stabilizeParityViewport } from '../lib/capture-stabilize.mjs';
import { preparePage } from '../lib/page-utils.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const packRoot = path.join(parityRoot, 'reference-pack');
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://df-impact.ddev.site').replace(/\/$/, '');
const packSlug = process.env.PACK_SLUG || 'homepage';
const drupalPath = process.env.DRUPAL_PATH || '/homepage';
const bp = process.env.PARITY_BP || 'desktop';

const viewports = { desktop: { width: 1440, height: 900 }, tablet: { width: 768, height: 1024 }, mobile: { width: 375, height: 812 } };
const selectors = JSON.parse(fs.readFileSync(path.join(packRoot, 'chrome-structural-selectors.json'), 'utf8')).selectors;

const STYLE_KEYS = [
  'font-family',
  'font-size',
  'font-weight',
  'line-height',
  'letter-spacing',
  'text-transform',
  'color',
  'width',
  'height',
  'margin',
  'padding',
  'display',
  'gap',
];

async function measure(page, selector) {
  const el = page.locator(selector).first();
  if (!(await el.count())) return null;
  return el.evaluate(
    (node, keys) => {
      const cs = getComputedStyle(node);
      const r = node.getBoundingClientRect();
      const styles = {};
      for (const k of keys) styles[k] = cs.getPropertyValue(k);
      const img = node.tagName === 'IMG' ? node : node.querySelector('img');
      let imageMeta = null;
      if (img) {
        imageMeta = { naturalWidth: img.naturalWidth, naturalHeight: img.naturalHeight, src: img.currentSrc || img.src };
      }
      return {
        rect: { x: r.x, y: r.y, w: r.width, h: r.height },
        styles,
        imageMeta,
      };
    },
    STYLE_KEYS,
  );
}

async function fontAudit(page) {
  return page.evaluate(async () => {
    const families = ['Montserrat', 'Roboto', 'Baskervville'];
    const loaded = {};
    if (document.fonts) {
      for (const f of families) {
        loaded[f] = document.fonts.check(`16px ${f}`) || document.fonts.check(`600 16px ${f}`);
      }
      await document.fonts.ready;
    }
    return { loaded, count: document.fonts?.size ?? 0 };
  });
}

const useLinuxRef = process.env.PARITY_COMPARE_LINUX === '1';
const server = useLinuxRef ? await startRefPackServer(packRoot, { drupalBase }) : null;
const browser = await chromium.launch();
const vp = viewports[bp] || viewports.desktop;
const refPage = await browser.newPage();
const drPage = await browser.newPage();
await preparePage(refPage);
await preparePage(drPage);
await refPage.setViewportSize(vp);
await drPage.setViewportSize(vp);

const refHtml = path.join(packRoot, packSlug, bp, 'rendered.html');
const refUrl = useLinuxRef
  ? `${server.baseUrl}/rendered/${packSlug}/${bp}`
  : `file://${refHtml}`;
await refPage.goto(refUrl, { waitUntil: 'load', timeout: 120000 });
await refPage.waitForTimeout(800);
await stabilizeParityViewport(refPage);
await drPage.goto(`${drupalBase}${drupalPath}`, { waitUntil: 'load', timeout: 120000 });
await drPage.waitForTimeout(800);
await stabilizeParityViewport(drPage);

const refFonts = await fontAudit(refPage);
const drFonts = await fontAudit(drPage);

const diffs = [];
for (const selector of selectors) {
  const ref = await measure(refPage, selector);
  const dr = await measure(drPage, selector);
  if (!ref && !dr) continue;
  if (!ref || !dr) {
    diffs.push({ selector, kind: 'missing', ref: !!ref, dr: !!dr, area: 99999 });
    continue;
  }
  const area = Math.max(ref.rect.w * ref.rect.h, dr.rect.w * dr.rect.h);
  for (const dim of ['x', 'y', 'w', 'h']) {
    const delta = Math.abs(ref.rect[dim] - dr.rect[dim]);
    if (delta > 2) {
      diffs.push({ selector, kind: 'rect', dim, ref: ref.rect[dim], dr: dr.rect[dim], delta, area });
    }
  }
  for (const key of STYLE_KEYS) {
    const a = (ref.styles[key] || '').trim();
    const b = (dr.styles[key] || '').trim();
    if (a !== b) {
      diffs.push({ selector, kind: 'style', key, ref: a, dr: b, area });
    }
  }
  if (ref.imageMeta || dr.imageMeta) {
    const rw = ref.imageMeta?.naturalWidth ?? 0;
    const dw = dr.imageMeta?.naturalWidth ?? 0;
    if (rw !== dw && (rw || dw)) {
      diffs.push({ selector, kind: 'imgNatural', ref: ref.imageMeta, dr: dr.imageMeta, area });
    }
  }
}

diffs.sort((a, b) => (b.area ?? 0) - (a.area ?? 0));
console.log(JSON.stringify({ packSlug, drupalPath, bp, refFonts, drFonts, diffCount: diffs.length, top: diffs.slice(0, 25) }, null, 2));

await browser.close();
if (server) await server.close();
