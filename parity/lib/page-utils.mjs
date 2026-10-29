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

const CONSENT_ROOT_IDS = [
  'onetrust-banner-sdk',
  'onetrust-pc-sdk',
  'onetrust-consent-sdk',
  'CybotCookiebotDialog',
  'cookiebanner',
];

export async function dismissOverlays(page) {
  const selectors = [
    'button:has-text("Accept")',
    'button:has-text("I Accept")',
    'button:has-text("Accept All")',
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
  await page.waitForTimeout(200);
  await page.evaluate((ids) => {
    for (const id of ids) {
      document.getElementById(id)?.remove();
    }
    document.querySelectorAll('[id^="onetrust-"], .cookiebot, #CybotCookiebotDialog').forEach((el) => {
      el.remove();
    });
  }, CONSENT_ROOT_IDS);
}

/** Remove consent layers that block pointer events on footer links (live WP). */
export async function ensurePointerClearForSelector(page, selector) {
  for (let attempt = 0; attempt < 3; attempt += 1) {
    await dismissOverlays(page);
    const blocked = await page.evaluate((sel) => {
      const el = document.querySelector(sel.split(',')[0].trim());
      if (!el) return false;
      const r = el.getBoundingClientRect();
      const top = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
      if (!top || top === el || el.contains(top)) return false;
      return Boolean(top.closest('#onetrust-banner-sdk, #onetrust-pc-sdk, [id^="onetrust-"], .cookiebot'));
    }, selector);
    if (!blocked) return;
    await page.waitForTimeout(300);
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

/** Bounded font wait — live WP never settles on document.fonts.ready. */
export async function waitForFonts(page, maxMs = 5000) {
  await Promise.race([
    page.evaluate(async () => {
      if (!document.fonts) {
        return;
      }
      const families = [
        '400 16px Montserrat',
        '600 16px Montserrat',
        '700 16px Montserrat',
        '400 16px Roboto',
        '600 16px Roboto',
        '400 16px "Baskervville"',
      ];
      await Promise.all(families.map((spec) => document.fonts.load(spec).catch(() => undefined)));
      await Promise.race([
        document.fonts.ready,
        new Promise((r) => setTimeout(r, 3500)),
      ]);
    }),
    page.waitForTimeout(maxMs),
  ]);
}

/**
 * Navigate with load (not networkidle) and a capped post-load settle for parity/behavior.
 */
export async function gotoWithBoundedSettle(page, url, { timeout = 90000, settleMs = 1200 } = {}) {
  await page.goto(url, { waitUntil: 'load', timeout });
  await dismissOverlays(page);
  await waitForFonts(page, Math.min(settleMs + 800, 6000));
  await page.waitForTimeout(Math.min(settleMs, 1500));
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
    .replace(/&lt;\/?sup&gt;/gi, '')
    .replace(/<sup>\s*®\s*<\/sup>/gi, '®')
    .replace(/&amp;/g, '&')
    .replace(/&nbsp;/g, ' ')
    .replace(/\)([A-Za-z])/g, ') $1')
    .replace(/\.([A-Za-z])/g, '. $1')
    .replace(/([a-z])([A-Z])/g, '$1 $2')
    .replace(/https?:\/\/(?:i\d+\.)?wp\.com\/danafarberimpact\.org/gi, 'https://danafarberimpact.org')
    .replace(/https?:\/\/(?:www\.)?danafarberimpact\.org/gi, '')
    .replace(/(\d)\s+by\b/gi, '$1by')
    .replace(/\bload more briefs\b/gi, '')
    .replace(/continue\s*reading[a-z]*/gi, '')
    .replace(/\bview all briefs\b/gi, '')
    .replace(/\bdana-farber\b/gi, 'dana farber')
    .replace(/(\w)(previous|next)(\d)/gi, '$1 $2 $3')
    .replace(/\bview all past issues\b/gi, '')
    .replace(/\bsee archives\b/gi, '')
    .replace(/\bdigital exclusives archives\b/gi, 'digital exclusives')
    .replace(/\bdigital exclusives digital exclusives\b/gi, 'digital exclusives')
    .replace(/\bcancer research archives\b/gi, 'cancer research')
    .replace(/[\u2018\u2019\u2032]/g, "'")
    .replace(/[\u201C\u201D]/g, '"')
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
