#!/usr/bin/env node
/**
 * Per-variant sample parity: text + component computed styles (WP fixtures vs Drupal).
 *
 *   REPORT_TAG=before node scripts/compare-article-variant-parity.mjs
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';
import { dismissOverlays, extractMainText, preparePage, textSimilarity } from '../lib/page-utils.mjs';
import { stabilizeParityViewport } from '../lib/capture-stabilize.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const fixtureRoot = path.join(parityRoot, 'cache', 'wp-article-variants');
const classificationPath = path.join(
  parityRoot,
  'reports/progress/article-variants-parity/variant-classification.json',
);
const componentsByVariant = JSON.parse(
  fs.readFileSync(path.join(parityRoot, 'config', 'article-variant-components.json'), 'utf8'),
);
const outDir = path.join(parityRoot, 'reports/progress/article-variants-parity');
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const minText = Number(process.env.PARITY_MIN_TEXT || '0.98');
const tag = process.env.REPORT_TAG || 'run';
const VARIANT_KEYS = (
  process.env.VARIANT_KEYS ||
  'hero,no_hero,sidebar,pull_quote,gallery,video_embed,long_form,custom_blocks'
).split(',');

const STYLE_KEYS = ['width', 'height', 'max-width', 'padding', 'margin', 'font-size', 'border'];

function pathSlug(p) {
  return p.replace(/^\/|\/$/g, '').replace(/\//g, '_') || 'root';
}

/** Eager-load lazy WP fixture images so component probes match live layout. */
function prepareWpFixtureHtml(html) {
  return html
    .replace(/\sloading="lazy"/gi, ' loading="eager"')
    .replace(/\sclass="([^"]*\blazyload\b[^"]*)"/gi, (match, classes) => {
      const next = classes.replace(/\blazyload\b/g, 'lazyloaded');
      return ` class="${next}"`;
    })
    .replace(/<img([^>]*?)\sdata-src="([^"]+)"([^>]*)>/gi, (full, before, src, after) => {
      if (/\ssrc="/i.test(before + after)) {
        return full;
      }
      return `<img${before} src="${src}" data-src="${src}"${after}>`;
    });
}

async function probeComponents(page, variant) {
  const defs = componentsByVariant[variant] || [];
  return page.evaluate(
    ({ defs, styleKeys }) => {
      const out = [];
      for (const def of defs) {
        const el = document.querySelector(def.selector);
        if (!el) {
          out.push({ id: def.id, selector: def.selector, found: false });
          continue;
        }
        const r = el.getBoundingClientRect();
        let w = Math.round(r.width);
        let h = Math.round(r.height);
        const cs = getComputedStyle(el);
        if (w === 0 && el.getAttribute('width')) {
          w = parseInt(el.getAttribute('width'), 10) || 0;
        }
        if (h === 0 && el.getAttribute('height')) {
          h = parseInt(el.getAttribute('height'), 10) || 0;
        }
        if (w === 0 && cs.width && cs.width.endsWith('px')) {
          w = Math.round(parseFloat(cs.width));
        }
        if (h === 0 && cs.height && cs.height.endsWith('px')) {
          h = Math.round(parseFloat(cs.height));
        }
        const inlineStyle = el.getAttribute('style') || '';
        const inlineW = inlineStyle.match(/width:\s*([\d.]+)px/i);
        const inlineH = inlineStyle.match(/height:\s*([\d.]+)px/i);
        if (w === 0 && inlineW) {
          w = Math.round(parseFloat(inlineW[1]));
        }
        if (h === 0 && inlineH) {
          h = Math.round(parseFloat(inlineH[1]));
        }
        if (w > 0 && h === 0 && el.naturalWidth > 0 && el.naturalHeight > 0) {
          h = Math.round(w * (el.naturalHeight / el.naturalWidth));
        }
        if (w > 0 && h === 0) {
          const src = el.getAttribute('src') || '';
          const derivative = src.match(/-(\d+)x(\d+)\./);
          if (derivative) {
            h = Math.round(w * (parseInt(derivative[2], 10) / parseInt(derivative[1], 10)));
          }
        }
        if (h > 0 && w === 0 && el.naturalWidth > 0 && el.naturalHeight > 0) {
          w = Math.round(h * (el.naturalWidth / el.naturalHeight));
        }
        if ((w === 0 || h === 0) && el.closest('figure.wp-block-image')) {
          const fr = el.closest('figure.wp-block-image').getBoundingClientRect();
          if (w === 0 && fr.width >= 200) {
            w = Math.round(fr.width);
          }
          if (h === 0 && fr.height >= 120) {
            h = Math.round(fr.height);
          }
        }
        const styles = {};
        for (const k of styleKeys) {
          styles[k] = cs[k];
        }
        let aspect = null;
        if (el.tagName === 'IFRAME' || el.querySelector('iframe')) {
          const iframe = el.tagName === 'IFRAME' ? el : el.querySelector('iframe');
          const ir = iframe.getBoundingClientRect();
          aspect = ir.width > 0 ? Math.round((ir.height / ir.width) * 1000) / 1000 : null;
        }
        out.push({
          id: def.id,
          selector: def.selector,
          found: true,
          rect: { w, h },
          aspect,
          styles,
        });
      }
      return out;
    },
    { defs, styleKeys: STYLE_KEYS },
  );
}

function diffComponents(wpComps, drComps) {
  const mismatches = [];
  const wpById = Object.fromEntries(wpComps.filter((c) => c.found).map((c) => [c.id, c]));
  const drById = Object.fromEntries(drComps.filter((c) => c.found).map((c) => [c.id, c]));
  for (const id of new Set([...Object.keys(wpById), ...Object.keys(drById)])) {
    const wp = wpById[id];
    const dr = drById[id];
    if (!wp && dr) mismatches.push({ id, issue: 'drupal_only' });
    else if (wp && !dr) mismatches.push({ id, issue: 'drupal_missing' });
    else if (wp && dr) {
      const rectDelta =
        Math.abs((wp.rect?.w || 0) - (dr.rect?.w || 0)) + Math.abs((wp.rect?.h || 0) - (dr.rect?.h || 0));
      const aspectDelta =
        wp.aspect != null && dr.aspect != null ? Math.abs(wp.aspect - dr.aspect) : 0;
      const styleDiffs = [];
      for (const k of STYLE_KEYS) {
        const a = (wp.styles?.[k] || '').replace(/\s+/g, ' ').trim();
        const b = (dr.styles?.[k] || '').replace(/\s+/g, ' ').trim();
        if (a === b) {
          continue;
        }
        if ((k === 'width' || k === 'height') && (a === '0px' || a === 'auto' || b === '0px')) {
          continue;
        }
        if (k === 'width' && rectDelta <= 4) {
          continue;
        }
        styleDiffs.push(k);
      }
      const rectLimit = id === 'pullquote' ? 120 : id === 'stackable' ? 48 : id === 'lead_figure' ? 96 : 24;
      if (rectDelta > rectLimit || aspectDelta > 0.08 || styleDiffs.length > 3) {
        mismatches.push({ id, issue: 'style_or_rect', rectDelta, aspectDelta, styleDiffs });
      }
    }
  }
  return mismatches;
}

const classification = JSON.parse(fs.readFileSync(classificationPath, 'utf8'));
const rows = [];
const browser = await chromium.launch();

for (const variant of VARIANT_KEYS.map((v) => v.trim()).filter(Boolean)) {
  const sample = classification.samples?.[variant];
  if (!sample?.paths?.length) continue;
  for (const p of sample.paths) {
    const slug = pathSlug(p);
    const fixture = path.join(fixtureRoot, `${slug}.html`);
    const row = { variant, path: p, textPct: null, textPass: false, componentMismatches: [], pass: false };
    if (!fs.existsSync(fixture)) {
      row.error = 'missing_fixture';
      rows.push(row);
      continue;
    }

    let wpText = '';
    let drText = '';
    let wpComps = [];
    let drComps = [];

    for (const [label, load] of [
      [
        'wp',
        async (page) => {
          const html = prepareWpFixtureHtml(fs.readFileSync(fixture, 'utf8'));
          await page.setContent(html, {
            waitUntil: 'domcontentloaded',
            timeout: 120000,
            url: 'https://danafarberimpact.org/',
          });
        },
      ],
      ['drupal', async (page) => page.goto(`${drupalBase}${p}`, { waitUntil: 'load', timeout: 120000 })],
    ]) {
      const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
      await preparePage(page);
      await load(page);
      await dismissOverlays(page);
      await page.waitForTimeout(700);
      await stabilizeParityViewport(page);
      await page
        .waitForFunction(
          () => {
            const selectors = [
              'main .entry-content figure.wp-block-image:first-of-type img',
              'main .article__body figure.wp-block-image:first-of-type img',
              'main .entry-content .wp-block-image.alignright img',
              'main .article__body .wp-block-image.alignright img',
            ];
            const imgs = selectors
              .map((sel) => document.querySelector(sel))
              .filter(Boolean);
            if (imgs.length === 0) {
              return true;
            }
            return imgs.some(
              (img) => img.complete && img.naturalWidth > 0 && img.getBoundingClientRect().width > 0,
            );
          },
          { timeout: 15000 },
        )
        .catch(() => {});
      const text = (await extractMainText(page)).text;
      const comps = await probeComponents(page, variant);
      if (label === 'wp') {
        wpText = text;
        wpComps = comps;
      }
      else {
        drText = text;
        drComps = comps;
      }
      await page.close();
    }

    row.textPct = Math.round(textSimilarity(wpText, drText) * 10000) / 100;
    row.textPass = row.textPct >= minText * 100;
    row.componentMismatches = diffComponents(wpComps, drComps);
    row.pass = row.textPass && row.componentMismatches.length === 0;
    rows.push(row);
  }
}

await browser.close();

const report = { generatedAt: new Date().toISOString(), tag, minTextPct: minText * 100, rows };
const outFile = path.join(outDir, `variant-parity-${tag}-${Date.now()}.json`);
fs.writeFileSync(outFile, JSON.stringify(report, null, 2));
console.log(JSON.stringify({ outFile, pass: rows.filter((r) => r.pass).length, total: rows.length }, null, 2));
