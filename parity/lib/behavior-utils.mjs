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
  try {
    await loc.hover({ force: true, timeout: 5000 });
  } catch {
    return null;
  }
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
export const DRUPAL_HERO_VIDEO =
  '.hero__cover-video, .homepage-banner video, .wp-block-cover__video-background';

export async function headerScrollState(page, selector = '.header, .desktop-header, .impact-header') {
  return page.evaluate((sel) => {
    const el = document.querySelector(sel);
    if (!el) return { found: false };
    const cs = getComputedStyle(el);
    return {
      found: true,
      className: el.className,
      scrolled: el.classList.contains('header--scrolled') || el.classList.contains('scrolled'),
      position: cs.position,
      boxShadow: cs.boxShadow,
      height: el.getBoundingClientRect().height,
    };
  }, selector);
}

export async function dropdownOpenOnHover(page, triggerSel, panelSel) {
  const trigger = page.locator(triggerSel).first();
  if (!(await trigger.count())) return { found: false };
  try {
    await trigger.hover({ force: true, timeout: 5000 });
  } catch {
    return { found: false };
  }
  await page.waitForTimeout(350);
  return page.evaluate((panel) => {
    const el = document.querySelector(panel);
    if (!el) return { found: false };
    const cs = getComputedStyle(el);
    return {
      found: true,
      display: cs.display,
      visibility: cs.visibility,
      opacity: cs.opacity,
      height: el.getBoundingClientRect().height,
    };
  }, panelSel);
}

export async function elementPresent(page, selector) {
  return page.evaluate((sel) => Boolean(document.querySelector(sel)), selector);
}

export async function revealState(page, selector = '.df-reveal, .df-reveal--visible, .ultp-animation') {
  return page.evaluate((sel) => {
    const els = [...document.querySelectorAll(sel)];
    return {
      count: els.length,
      visible: els.filter((el) => el.classList.contains('df-reveal--visible')).length,
    };
  }, selector);
}

export function behaviorOutDir(parityRoot, runId, site, slug) {
  return `${parityRoot}/reports/behavior/${runId}/${site}/${slug}`;
}
