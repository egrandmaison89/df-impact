import {
  computedStyle,
  computedStyleHover,
  scrollSelectorIntoView,
  dropdownOpenOnHover,
  DRUPAL_HERO_VIDEO,
  elementPresent,
  headerScrollState,
  headerLayoutBox,
  revealState,
  videoState,
  WP_HERO_VIDEO,
} from './behavior-utils.mjs';
import { ensurePointerClearForSelector } from './page-utils.mjs';
import { colorsClose } from './behavior-compare.mjs';

export const WP_PROFILE = {
  site: 'wp',
  heroVideo: WP_HERO_VIDEO,
  selectors: {
    header: '.desktop-header, .impact-header, .header',
    navLink: '#mega-menu-max_mega_menu_1 > li.mega-menu-item > a.mega-menu-link',
    issuesTrigger: '#mega-menu-item-599 > a.mega-menu-link',
    issuesPanel: '#mega-menu-item-599 > ul.mega-sub-menu',
    donate: '.desktop-header .wp-block-button a[data-analytics-text="donate"]',
    footerLink: 'footer .copyright a, p.copyright a',
    bodyLink: 'main .entry-content a, main .wp-block-post-content a, main a',
    search: '.header__search-link, .astm-search-menu a, .is-search-icon',
    menuToggle: '.mobile-header .mega-toggle-label, .mega-menu-toggle',
    drawer: '.mobile-header .mega-menu-wrap, .mega-menu-wrap',
    card: 'main .ultp-block-item, main .wp-block-post, main article',
    button: 'main .wp-block-button a.wp-block-button__link.has-background',
    pagerLink:
      'main .wp-block-query-pagination a, main .pagination a, main nav.pager a, main .pager__item a',
    categoryLink: 'main .wp-block-categories-list a, main .archive a[href*="/category/"]',
    inBriefButton: 'main .in-brief-button a, .in-brief-button a.wp-block-button__link',
  },
  reveal: '.ultp-animation, .homepage-section, [class*="ultp-animation"]',
};

export const DRUPAL_PROFILE = {
  site: 'drupal',
  heroVideo: DRUPAL_HERO_VIDEO,
  selectors: {
    header: '.header, .desktop-header, .impact-header',
    navLink: '.header__nav-link, .mega-menu-link, header nav a.mega-menu-link',
    issuesTrigger: '#mega-menu-item-599 > a.mega-menu-link, .header__nav-item--issues > .header__nav-link',
    issuesPanel: '#mega-menu-item-599 > ul.mega-sub-menu, .header__nav-item--issues .header__dropdown',
    donate: '.desktop-header .wp-block-button a[data-analytics-text="donate"]',
    footerLink: 'footer .copyright a, p.copyright a',
    bodyLink: 'main .entry-content a, main .wp-block-post-content a, main a.has-underline-text-decoration',
    search: '.header__search-link, .header-search a, .is-search-icon, .astm-search-menu a',
    menuToggle: '.mobile-header .mega-toggle-label, .header__menu-toggle, .mega-menu-toggle',
    drawer: '.header__drawer, .mobile-header .mega-menu-wrap, #mega-menu-max_mega_menu_1',
    card: 'main .ultp-block-item, main .views-row, main article',
    button: 'main .wp-block-button a.wp-block-button__link.has-background',
    pagerLink:
      'main .wp-block-query-pagination a, main .pagination a, main nav.pager a, main .pager__item a',
    categoryLink: 'main .wp-block-categories-list a, main .archive a[href*="/category/"]',
    inBriefButton: 'main .in-brief-button a, .in-brief-button a.wp-block-button__link',
  },
  reveal: '.df-reveal, .df-reveal--visible, .ultp-animation, .homepage-section',
};

export async function widgetInventory(page) {
  return page.evaluate(() => {
    const isConsent = (el) =>
      Boolean(
        el.closest(
          '#onetrust-pc-sdk, #onetrust-banner-sdk, #cookiebanner, .cookiebot, #CybotCookiebotDialog',
        ),
      );
    const scope = document.querySelector('main') || document.querySelector('.wp-site-blocks') || document.body;
    const carousels = [...scope.querySelectorAll('[data-slick], .slick-slider, .swiper, .ultp-slider, .owl-carousel')]
      .filter((el) => !isConsent(el)).length;
    const accordions = [...scope.querySelectorAll('details.wp-block-details, .wp-block-details.is-style-default')]
      .filter((el) => !isConsent(el)).length;
    return { carousels, accordions };
  });
}

export async function captureSiteBehavior(page, profile, bp) {
  const s = profile.selectors;
  const data = {};

  data.hero = await videoState(page, profile.heroVideo);
  data.navDefault = await computedStyle(page, s.navLink);
  data.headerBeforeScroll = await headerScrollState(page, s.header);
  data.headerScrollDepths = {};
  const headerPresent = await elementPresent(page, s.header);
  if (headerPresent) {
    for (const y of [600, 2000]) {
      await page.evaluate((scrollY) => window.scrollTo(0, scrollY), y);
      await page.waitForTimeout(500);
      data.headerScrollDepths[`y${y}`] = await headerLayoutBox(page, s.header);
    }
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.waitForTimeout(250);
  }
  if (s.pagerLink) {
    data.pagerDefault = await computedStyle(page, s.pagerLink);
    data.pagerHover = await computedStyleHover(page, s.pagerLink);
  }
  if (s.categoryLink) {
    data.categoryDefault = await computedStyle(page, s.categoryLink);
    data.categoryHover = await computedStyleHover(page, s.categoryLink);
  }
  if (s.inBriefButton) {
    data.inBriefButtonDefault = await computedStyle(page, s.inBriefButton);
    data.inBriefButtonHover = await computedStyleHover(page, s.inBriefButton);
  }
  await ensurePointerClearForSelector(page, s.footerLink);
  await scrollSelectorIntoView(page, s.footerLink);
  data.footerDefault = await computedStyle(page, s.footerLink);
  data.footerLinkHover = await computedStyleHover(page, s.footerLink);
  if (
    profile.site === 'wp' &&
    data.footerDefault &&
    data.footerLinkHover &&
    colorsClose(data.footerDefault.color, data.footerLinkHover.color)
  ) {
    await ensurePointerClearForSelector(page, s.footerLink);
    await scrollSelectorIntoView(page, s.footerLink);
    data.footerLinkHover = await computedStyleHover(page, s.footerLink);
  }
  data.navHover = await computedStyleHover(page, s.navLink);
  data.donateDefault = await computedStyle(page, s.donate);
  data.donateHover = await computedStyleHover(page, s.donate);
  if (s.bodyLink) {
    await scrollSelectorIntoView(page, s.bodyLink);
  }
  data.bodyLinkDefault = await computedStyle(page, s.bodyLink);
  data.bodyLinkHover = await computedStyleHover(page, s.bodyLink);
  data.cardDefault = await computedStyle(page, s.card);
  data.cardHover = await computedStyleHover(page, s.card);
  data.buttonDefault = await computedStyle(page, s.button);
  data.buttonHover = await computedStyleHover(page, s.button);
  data.gtranslate = await elementPresent(page, '.gtranslate_wrapper, #gtranslate_selector, #gt-wrapper-23849622');
  data.searchToggle = await elementPresent(page, s.search);
  data.reveal = await revealState(page, profile.reveal);
  data.widgets = await widgetInventory(page);

  data.issuesDropdown =
    bp.label === 'desktop'
      ? await dropdownOpenOnHover(page, s.issuesTrigger, s.issuesPanel)
      : { found: false, skipped: true };

  await page.evaluate(() => window.scrollTo(0, 200));
  await page.waitForTimeout(300);
  data.headerScrollStyle = await computedStyle(page, s.header);
  data.headerAfterScroll = await headerScrollState(page, s.header);

  if (bp.label === 'mobile') {
    data.mobileMenu = await mobileMenuToggle(page, s.menuToggle, s.drawer);
  }

  return data;
}

async function mobileMenuToggle(page, toggleSel, drawerSel) {
  try {
    const clicked = await page.evaluate((sel) => {
      const el = document.querySelector(sel);
      if (!el) return false;
      el.dispatchEvent(new MouseEvent('click', { bubbles: true }));
      return true;
    }, toggleSel);
    if (!clicked) return { opened: false, drawerStyle: null };
    await page.waitForTimeout(450);
    const drawerStyle = await computedStyle(page, drawerSel);
    const opened = await page.evaluate(() => {
      const wrap = document.querySelector('.mobile-header .mega-menu-wrap, .mega-menu-wrap');
      const menu = wrap?.querySelector('.mega-menu');
      return Boolean(
        wrap?.classList.contains('mega-menu-open') ||
          menu?.classList.contains('mega-menu-open') ||
          document.querySelector('.mega-toggle-label')?.getAttribute('aria-expanded') === 'true' ||
          document.querySelector('.header__drawer--open'),
      );
    });
    return { opened, drawerStyle };
  } catch {
    return { opened: false, drawerStyle: null };
  }
}

export async function captureReducedMotionHero(page, profile) {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.reload({ waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(400);
  return videoState(page, profile.heroVideo);
}
