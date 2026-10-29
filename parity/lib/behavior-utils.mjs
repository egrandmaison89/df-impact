/** Dynamic behavior capture helpers (transitions enabled — not static parity prep). */

export const BEHAVIOR_BREAKPOINTS = [
  { label: 'desktop', width: 1440, height: 900 },
  { label: 'tablet', width: 768, height: 900 },
  { label: 'mobile', width: 375, height: 812 },
];

export async function prepareBehaviorPage(page, { reducedMotion = false } = {}) {
  await page.emulateMedia({ reducedMotion: reducedMotion ? 'reduce' : 'no-preference' });
  await page.addInitScript(() => {
    const purgeConsent = () => {
      document
        .querySelectorAll('#onetrust-banner-sdk, #onetrust-pc-sdk, [id^="onetrust-"], .cookiebot, #CybotCookiebotDialog')
        .forEach((el) => el.remove());
    };
    purgeConsent();
    new MutationObserver(purgeConsent).observe(document.documentElement, { childList: true, subtree: true });
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

export async function scrollSelectorIntoView(page, selector) {
  const first = selector.split(',')[0].trim();
  await page.evaluate((sel) => {
    document.querySelector(sel)?.scrollIntoView({ block: 'center', inline: 'nearest' });
  }, first);
  await page.waitForTimeout(250);
}

export async function computedStyleHover(page, selector) {
  const loc = page.locator(selector).first();
  if ((await loc.count()) === 0) return null;
  for (let attempt = 0; attempt < 3; attempt += 1) {
    try {
      await page.mouse.move(8, 8);
      await page.waitForTimeout(80);
      await loc.scrollIntoViewIfNeeded();
      const box = await loc.boundingBox();
      if (box) {
        await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2, { steps: 10 });
      }
      await loc.hover({ timeout: 8000 });
      await page.waitForTimeout(650);
      let hovering = await loc.evaluate((el) => el.matches(':hover'));
      if (!hovering && box) {
        const cdp = await page.context().newCDPSession(page);
        const x = box.x + box.width / 2;
        const y = box.y + box.height / 2;
        await cdp.send('Input.dispatchMouseEvent', { type: 'mouseMoved', x, y });
        await page.waitForTimeout(700);
        hovering = await loc.evaluate((el) => el.matches(':hover'));
      }
      if (hovering) break;
    } catch {
      if (attempt === 2) {
        try {
          await loc.hover({ force: true, timeout: 5000 });
          await page.waitForTimeout(650);
        } catch {
          return null;
        }
      }
    }
  }
  return loc.evaluate((el) => {
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
  });
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

export async function headerLayoutBox(page, selector = '.desktop-header, .impact-header, .header') {
  return page.evaluate((sel) => {
    const el =
      document.querySelector('.desktop-header') ||
      document.querySelector('.impact-header') ||
      document.querySelector('.header');
    if (!el) return { found: false };
    const r = el.getBoundingClientRect();
    const logo = document.querySelector('.header-logo');
    const lr = logo?.getBoundingClientRect();
    const visibleSelects = [...document.querySelectorAll('.gtranslate_wrapper select')].filter((node) => {
      const cs = getComputedStyle(node);
      if (cs.display === 'none' || cs.visibility === 'hidden') return false;
      const pr = node.getBoundingClientRect();
      return pr.width > 0 && pr.height > 0;
    });
    return {
      found: true,
      scrollY: window.scrollY,
      header: { x: r.x, y: r.y, width: r.width, height: r.height },
      logo: lr ? { x: lr.x, y: lr.y, width: lr.width, height: lr.height } : null,
      gtranslateVisibleSelects: visibleSelects.length,
    };
  }, selector);
}

export async function headerScrollState(page, selector = '.header, .desktop-header, .impact-header') {
  return page.evaluate((sel) => {
    const el = document.querySelector(sel);
    if (!el) return { found: false };
    const cs = getComputedStyle(el);
    const logo = document.querySelector('.header-logo');
    const scrolledClass =
      el.classList.contains('header--scrolled') ||
      el.classList.contains('scrolled') ||
      Boolean(el.querySelector('.scrolled')) ||
      Boolean(logo?.classList.contains('scrolled'));
    return {
      found: true,
      className: el.className,
      scrolled: scrolledClass,
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
