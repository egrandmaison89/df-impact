/** Shared page prep and content extraction for parity tests. */

import { EXTRACT_MAIN_TEXT_FN } from './content-region.mjs';

export const BREAKPOINTS = {
  'parity-desktop': { width: 1440, label: 'desktop' },
  'parity-tablet': { width: 768, label: 'tablet' },
  'parity-mobile': { width: 375, label: 'mobile' },
};

export async function preparePage(page) {
  await page.addInitScript(() => {
    window.__parity = { animationsDisabled: true };
    const style = document.createElement('style');
    style.textContent = `
      *, *::before, *::after {
        animation-duration: 0s !important;
        animation-delay: 0s !important;
        transition-duration: 0s !important;
        transition-delay: 0s !important;
        scroll-behavior: auto !important;
      }
    `;
    document.documentElement.appendChild(style);
  });

  await page.route('**/*', async (route) => {
    const url = route.request().url();
    if (/cookiebot|onetrust|osano|trustarc|quantcast|iubenda/i.test(url)) {
      await route.abort();
      return;
    }
    await route.continue();
  });
}

export async function dismissOverlays(page) {
  const selectors = [
    'button:has-text("Accept")',
    'button:has-text("I Accept")',
    'button:has-text("Got it")',
    '#onetrust-accept-btn-handler',
    '.cc-dismiss',
  ];
  for (const sel of selectors) {
    const el = page.locator(sel).first();
    if (await el.isVisible().catch(() => false)) {
      await el.click({ timeout: 2000 }).catch(() => {});
    }
  }
}

export async function scrollForLazyLoad(page) {
  await page.evaluate(async () => {
    const delay = (ms) => new Promise((r) => setTimeout(r, ms));
    const h = document.body.scrollHeight;
    const step = Math.max(400, Math.floor(window.innerHeight * 0.8));
    for (let y = 0; y < h; y += step) {
      window.scrollTo(0, y);
      await delay(80);
    }
    window.scrollTo(0, 0);
    await delay(200);
  });
}

export async function waitForFonts(page) {
  await page.evaluate(async () => {
    if (document.fonts && document.fonts.ready) {
      await document.fonts.ready;
    }
  });
}

export async function extractMainText(page) {
  return page.evaluate(EXTRACT_MAIN_TEXT_FN);
}

export async function extractMeta(page) {
  return page.evaluate(() => {
    const title = document.title || '';
    const pick = (sel) => document.querySelector(sel)?.getAttribute('content') || '';
    return {
      title,
      description: pick('meta[name="description"]'),
      ogTitle: pick('meta[property="og:title"]'),
      ogDescription: pick('meta[property="og:description"]'),
      ogImage: pick('meta[property="og:image"]'),
      canonical: document.querySelector('link[rel="canonical"]')?.getAttribute('href') || '',
      jsonLd: (() => {
        const el =
          document.querySelector('script[type="application/ld+json"].yoast-schema-graph') ||
          document.querySelector('script[type="application/ld+json"]');
        return el?.textContent?.trim() || '';
      })(),
    };
  });
}

export async function sampleComputedStyles(page, selectors) {
  return page.evaluate((sels) => {
    const out = {};
    for (const [key, selector] of Object.entries(sels)) {
      const el = document.querySelector(selector);
      if (!el) {
        out[key] = null;
        continue;
      }
      const cs = getComputedStyle(el);
      out[key] = {
        fontFamily: cs.fontFamily,
        fontSize: cs.fontSize,
        fontWeight: cs.fontWeight,
        lineHeight: cs.lineHeight,
        color: cs.color,
        backgroundColor: cs.backgroundColor,
        marginTop: cs.marginTop,
        marginBottom: cs.marginBottom,
        paddingTop: cs.paddingTop,
        paddingBottom: cs.paddingBottom,
      };
    }
    return out;
  }, selectors);
}

export function normalizeText(s) {
  return (s || '')
    .replace(/\u00a0/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .toLowerCase();
}

export function textSimilarity(a, b) {
  const na = normalizeText(a);
  const nb = normalizeText(b);
  if (!na && !nb) return 1;
  if (!na || !nb) return 0;
  if (na === nb) return 1;
  const wa = new Set(na.split(' '));
  const wb = new Set(nb.split(' '));
  let inter = 0;
  for (const w of wa) {
    if (wb.has(w)) inter += 1;
  }
  return inter / Math.max(wa.size, wb.size, 1);
}
