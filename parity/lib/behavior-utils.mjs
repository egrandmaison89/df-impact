/** Dynamic behavior capture helpers (transitions enabled — not static parity prep). */

export const BEHAVIOR_BREAKPOINTS = [
  { label: 'desktop', width: 1440, height: 900 },
  { label: 'tablet', width: 768, height: 900 },
  { label: 'mobile', width: 375, height: 812 },
];

export async function prepareBehaviorPage(page, { reducedMotion = false } = {}) {
  await page.emulateMedia({ reducedMotion: reducedMotion ? 'reduce' : 'no-preference' });
  await page.route('**/*', async (route) => {
    const url = route.request().url();
    if (/cookiebot|onetrust|osano|trustarc|quantcast|iubenda/i.test(url)) {
      await route.abort();
      return;
    }
    await route.continue();
  });
}

export async function computedStyle(page, selector) {
  return page.evaluate((sel) => {
    const el = document.querySelector(sel);
    if (!el) return null;
    const cs = getComputedStyle(el);
    return {
      color: cs.color,
      backgroundColor: cs.backgroundColor,
      transitionProperty: cs.transitionProperty,
      transitionDuration: cs.transitionDuration,
      transitionTimingFunction: cs.transitionTimingFunction,
      transform: cs.transform,
      position: cs.position,
      boxShadow: cs.boxShadow,
    };
  }, selector);
}

export async function computedStyleHover(page, selector) {
  const loc = page.locator(selector).first();
  if (!(await loc.count())) return null;
  await loc.hover({ force: true });
  await page.waitForTimeout(150);
  return computedStyle(page, selector);
}

export async function videoState(page, selector) {
  return page.evaluate(async (sel) => {
    const v = document.querySelector(sel);
    if (!v || v.tagName !== 'VIDEO') return { found: false };
    const paused = v.paused;
    const t0 = v.currentTime;
    await new Promise((r) => setTimeout(r, 600));
    return {
      found: true,
      src: v.currentSrc || v.getAttribute('src') || '',
      poster: v.poster || '',
      autoplay: v.autoplay,
      muted: v.muted,
      loop: v.loop,
      playsInline: v.playsInline,
      controls: v.controls,
      paused,
      currentTimeAdvanced: v.currentTime > t0 + 0.05,
      objectFit: getComputedStyle(v).objectFit,
      width: v.clientWidth,
      height: v.clientHeight,
    };
  }, selector);
}

export const WP_HERO_VIDEO = '.homepage-banner video, .wp-block-cover__video-background';
export const DRUPAL_HERO_VIDEO = '.hero__cover-video, .homepage-banner video';

export function behaviorOutDir(parityRoot, runId, site, slug) {
  return `${parityRoot}/reports/behavior/${runId}/${site}/${slug}`;
}
