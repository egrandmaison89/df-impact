import {
  computedStyle,
  computedStyleHover,
  dropdownOpenOnHover,
  DRUPAL_HERO_VIDEO,
  elementPresent,
  headerScrollState,
  revealState,
  videoState,
  WP_HERO_VIDEO,
} from './behavior-utils.mjs';

export const WP_PROFILE = {
  site: 'wp',
  heroVideo: WP_HERO_VIDEO,
  selectors: {
    header: '.desktop-header, .impact-header, .header',
    navLink: '#mega-menu-max_mega_menu_1 > li.mega-menu-item > a.mega-menu-link',
    issuesTrigger:
      '#mega-menu-item-599, #mega-menu-max_mega_menu_1 li.mega-menu-item-has-children > a.mega-menu-link',
    issuesPanel: '#mega-menu-item-599 ul.mega-sub-menu, ul.mega-sub-menu',
    donate:
      'a.homepage-banner-button, .desktop-header a.wp-block-button__link, .header__donate, a[data-analytics-text="donate"]',
    footerLink: 'footer .copyright a, footer a',
    bodyLink: 'main .entry-content a, main .wp-block-post-content a, main a',
    search: '.header__search-link, .astm-search-menu a, .is-search-icon',
    menuToggle: '.mobile-header .mega-toggle-label, .mega-menu-toggle',
    drawer: '.mobile-header .mega-menu-wrap, .mega-menu-wrap',
    card: 'main .ultp-block-item, main .wp-block-post, main article',
    button: 'main a.wp-block-button__link, main .wp-block-button__link',
  },
  reveal: '.ultp-animation, .homepage-section, [class*="ultp-animation"]',
};

export const DRUPAL_PROFILE = {
  site: 'drupal',
  heroVideo: DRUPAL_HERO_VIDEO,
  selectors: {
    header: '.header, .desktop-header, .impact-header',
    navLink: '.header__nav-link, .mega-menu-link, header nav a.mega-menu-link',
    issuesTrigger: '.header__nav-item--issues, .mega-menu-item-has-children, #mega-menu-item-599',
    issuesPanel: '.header__dropdown, .mega-sub-menu',
    donate:
      '.header__donate, .homepage-banner-button a, a.homepage-banner-button, a[data-analytics-text="donate"], .desktop-header a.wp-block-button__link',
    footerLink: 'footer a, .copyright a',
    bodyLink: 'main .entry-content a, main .wp-block-post-content a, main a.has-underline-text-decoration',
    search: '.header__search-link, .header-search a, .is-search-icon, .astm-search-menu a',
    menuToggle: '.mobile-header .mega-toggle-label, .header__menu-toggle, .mega-menu-toggle',
    drawer: '.header__drawer, .mobile-header .mega-menu-wrap, #mega-menu-max_mega_menu_1',
    card: 'main .ultp-block-item, main .views-row, main article',
    button: 'main a.wp-block-button__link, main .wp-block-button__link',
  },
  reveal: '.df-reveal, .df-reveal--visible, .ultp-animation, .homepage-section',
};

export async function widgetInventory(page) {
  return page.evaluate(() => {
    const carousels = document.querySelectorAll(
      '[data-slick], .slick-slider, .swiper, .swiper-container, .ultp-slider, .owl-carousel',
    ).length;
    const accordions = document.querySelectorAll(
      '.accordion, [class*="accordion"], details.accordion, .wp-block-details',
    ).length;
    return { carousels, accordions };
  });
}

export async function captureSiteBehavior(page, profile, bp) {
  const s = profile.selectors;
  const data = {};

  data.hero = await videoState(page, profile.heroVideo);
  data.navDefault = await computedStyle(page, s.navLink);
  data.navHover = await computedStyleHover(page, s.navLink);
  data.headerBeforeScroll = await headerScrollState(page, s.header);
  data.donateDefault = await computedStyle(page, s.donate);
  data.donateHover = await computedStyleHover(page, s.donate);
  data.footerLinkHover = await computedStyleHover(page, s.footerLink);
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

  await page.evaluate(() => window.scrollTo(0, 120));
  await page.waitForTimeout(250);
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
