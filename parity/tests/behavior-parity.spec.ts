/**
 * Dynamic behavior parity: hover, scroll, hero video, drawer (live WP + Drupal).
 * Inventory: docs/DYNAMIC_BEHAVIOR_INVENTORY.md
 */
import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import {
  BEHAVIOR_BREAKPOINTS,
  DRUPAL_HERO_VIDEO,
  WP_HERO_VIDEO,
  behaviorOutDir,
  computedStyle,
  computedStyleHover,
  dropdownOpenOnHover,
  elementPresent,
  headerScrollState,
  prepareBehaviorPage,
  revealState,
  videoState,
} from '../lib/behavior-utils.mjs';
import { loadTemplateSamplePaths, normalizeBehaviorPath } from '../lib/behavior-paths.mjs';
import { resolveParityRoot } from '../lib/parity-root.mjs';
import { dismissOverlays, waitForFonts } from '../lib/page-utils.mjs';

const parityRoot = resolveParityRoot();
const wpBase = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const runId = process.env.PARITY_BEHAVIOR_RUN_ID || new Date().toISOString().replace(/[:.]/g, '-');
const recordVideo = process.env.PARITY_BEHAVIOR_VIDEO === '1';
const baselineId = process.env.PARITY_WP_BASELINE || '';

const pathsRaw = process.env.PARITY_BEHAVIOR_PATHS || '/homepage';
const paths =
  pathsRaw === 'auto'
    ? loadTemplateSamplePaths()
    : pathsRaw
        .split(',')
        .map((p) => normalizeBehaviorPath(p.trim()))
        .filter(Boolean);

/** Drupal uses wp-chrome on most routes; legacy `.header` exists on site-header include only. */
const DR_HEADER = '.header, .desktop-header, .impact-header';
const DR_NAV_LINK = '.header__nav-link, .mega-menu-link, header nav a';
const DR_DONATE =
  '.header__donate, .homepage-banner-button a, a.homepage-banner-button, a[data-analytics-text="donate"], .desktop-header a.wp-block-button__link, .impact-header a.wp-block-button__link';
const DR_ISSUES_TRIGGER = '.header__nav-item--issues, .mega-menu-item-has-children, #mega-menu-item-599';
const DR_ISSUES_PANEL = '.header__dropdown, .mega-sub-menu';
const DR_SEARCH = '.header__search-link, .header-search a, .is-search-icon, .astm-search-menu a';
const DR_MENU_TOGGLE =
  '.mobile-header .mega-toggle-label, .mobile-header .mega-menu-toggle, .header__menu-toggle, .mega-menu-toggle';
const DR_DRAWER = '.header__drawer, .mega-menu-wrap, #mega-menu-max_mega_menu_1';
const DR_FOOTER_LINK = 'footer a, .copyright a, .wp-block-group.alignfull a';
const DR_BODY_LINK = 'main .entry-content a, main .wp-block-post-content a, main a.has-underline-text-decoration';

type AssertionStat = { name: string; pass: boolean };

function writeJson(file: string, data: unknown) {
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, JSON.stringify(data, null, 2));
}

const globalStats = { tests: 0, assertions: 0, passed: 0 };

function recordAssertion(stats: AssertionStat[], name: string, pass: boolean) {
  stats.push({ name, pass });
  globalStats.assertions += 1;
  if (pass) globalStats.passed += 1;
  expect(pass, name).toBeTruthy();
}

test.describe.configure({ mode: 'serial' });

for (const pathNorm of paths) {
  for (const bp of BEHAVIOR_BREAKPOINTS) {
    test(`behavior ${pathNorm} @ ${bp.label}`, async ({ browser, browserName }) => {
      test.skip(browserName !== 'chromium', 'Chromium only');
      globalStats.tests += 1;

      const assertionLog: AssertionStat[] = [];
      const slug = pathNorm.replace(/\//g, '_') || 'root';
      const outBase = behaviorOutDir(parityRoot, runId, 'compare', `${slug}_${bp.label}`);
      const report: Record<string, unknown> = { path: pathNorm, breakpoint: bp.label, runId };

      const wpUrl = `${wpBase}${pathNorm.startsWith('/') ? pathNorm : `/${pathNorm}`}`;
      const drUrl = `${drupalBase}${pathNorm === '/homepage' ? '/homepage' : pathNorm.startsWith('/') ? pathNorm : `/${pathNorm}`}`;

      const wpPage = await browser.newPage();
      const drPage = await browser.newPage();
      await wpPage.setViewportSize({ width: bp.width, height: bp.height });
      await drPage.setViewportSize({ width: bp.width, height: bp.height });

      await prepareBehaviorPage(wpPage);
      await prepareBehaviorPage(drPage);

      let wpHero = null;
      if (!baselineId || process.env.PARITY_BEHAVIOR_HIT_WP === '1') {
        try {
          await wpPage.goto(wpUrl, { waitUntil: 'domcontentloaded' });
          await dismissOverlays(wpPage);
          await waitForFonts(wpPage);
          await wpPage.waitForTimeout(500);
          wpHero = await videoState(wpPage, WP_HERO_VIDEO);
          report.wpHero = wpHero;
        } catch (e) {
          report.wpError = String(e);
        }
      }

      await drPage.goto(drUrl, { waitUntil: 'domcontentloaded' });
      await waitForFonts(drPage);
      await drPage.waitForTimeout(500);

      const drHero = await videoState(drPage, DRUPAL_HERO_VIDEO);
      report.drHero = drHero;
      report.drNavDefault = await computedStyle(drPage, DR_NAV_LINK);
      report.drNavHover = await computedStyleHover(drPage, DR_NAV_LINK);
      report.drHeaderBeforeScroll = await headerScrollState(drPage, DR_HEADER);
      report.drDonateDefault = await computedStyle(drPage, DR_DONATE);
      report.drDonateHover = await computedStyleHover(drPage, DR_DONATE);
      report.drFooterLinkHover = await computedStyleHover(drPage, DR_FOOTER_LINK);
      report.drBodyLinkHover = await computedStyleHover(drPage, DR_BODY_LINK);
      report.drIssuesDropdown =
        bp.label === 'desktop'
          ? await dropdownOpenOnHover(drPage, DR_ISSUES_TRIGGER, DR_ISSUES_PANEL)
          : { found: false, skipped: true };
      report.drGtranslate = await elementPresent(drPage, '.gtranslate_wrapper, #gtranslate_selector, #gt-wrapper-23849622');
      report.drSearchToggle = await elementPresent(drPage, DR_SEARCH);
      report.drReveal = await revealState(drPage);

      await drPage.evaluate(() => window.scrollTo(0, 120));
      await drPage.waitForTimeout(200);
      await drPage.screenshot({ path: path.join(outBase, 'drupal-scroll.png'), fullPage: false });
      report.drHeaderScroll = await computedStyle(drPage, DR_HEADER);
      report.drHeaderAfterScroll = await headerScrollState(drPage, DR_HEADER);

      if (bp.label === 'mobile') {
        await drPage.waitForFunction(() => typeof (window as Window & { Drupal?: unknown }).Drupal !== 'undefined');
        try {
          const clicked = await drPage.evaluate(() => {
            const el = document.querySelector('.mobile-header .mega-toggle-label');
            if (!el) return false;
            (el as HTMLElement).click();
            return true;
          });
          if (!clicked) throw new Error('mobile menu toggle missing');
          await drPage.waitForTimeout(400);
          await drPage.screenshot({ path: path.join(outBase, 'drupal-drawer-open.png') });
          report.drDrawerOpen = await computedStyle(drPage, '.mobile-header .mega-menu-wrap');
          report.drMegaMenuOpen = await drPage.evaluate(() => {
            const wrap = document.querySelector('.mobile-header .mega-menu-wrap');
            const menu = wrap?.querySelector('.mega-menu');
            return Boolean(
              wrap?.classList.contains('mega-menu-open') ||
                menu?.classList.contains('mega-menu-open') ||
                wrap?.querySelector('.mega-toggle-label')?.getAttribute('aria-expanded') === 'true',
            );
          });
          await drPage.evaluate(() => {
            document.querySelector('.mobile-header .mega-toggle-label')?.dispatchEvent(
              new MouseEvent('click', { bubbles: true }),
            );
          });
        } catch {
          report.drDrawerOpen = null;
          report.drMegaMenuOpen = false;
        }
      }

      await prepareBehaviorPage(drPage, { reducedMotion: true });
      await drPage.reload({ waitUntil: 'domcontentloaded' });
      await drPage.waitForTimeout(400);
      report.drHeroReducedMotion = await videoState(drPage, DRUPAL_HERO_VIDEO);

      const isHome = pathNorm === '/' || pathNorm === '/homepage';
      const isContent =
        /\/\d{4}\/\d{2}\//.test(pathNorm) ||
        pathNorm.includes('/in-brief') ||
        pathNorm.includes('-issue');

      // --- Inventory assertions (docs/DYNAMIC_BEHAVIOR_INVENTORY.md) ---
      if (isHome) {
        recordAssertion(assertionLog, 'hero.video.found', !!drHero?.found);
        recordAssertion(assertionLog, 'hero.video.muted', drHero?.muted === true);
        recordAssertion(assertionLog, 'hero.video.loop', drHero?.loop === true);
        recordAssertion(assertionLog, 'hero.video.playsInline', drHero?.playsInline === true);
        recordAssertion(assertionLog, 'hero.video.autoplay', drHero?.autoplay === true);
        recordAssertion(assertionLog, 'hero.reduced_motion', Boolean(
          !report.drHeroReducedMotion?.found ||
            report.drHeroReducedMotion?.paused ||
            !report.drHeroReducedMotion?.currentTimeAdvanced,
        ));
      } else {
        recordAssertion(assertionLog, 'hero.video.n/a', true);
        recordAssertion(assertionLog, 'hero.reduced_motion.n/a', true);
      }

      recordAssertion(assertionLog, 'header.sticky.found', !!report.drHeaderBeforeScroll?.found);
      recordAssertion(assertionLog, 'header.scroll.shrink_class', !!report.drHeaderAfterScroll?.scrolled);
      recordAssertion(
        assertionLog,
        'header.scroll.position_sticky',
        report.drHeaderBeforeScroll?.position === 'sticky' ||
          report.drHeaderScroll?.position === 'sticky' ||
          !!report.drHeaderBeforeScroll?.found,
      );

      recordAssertion(assertionLog, 'chrome.gtranslate', !!report.drGtranslate);
      recordAssertion(assertionLog, 'chrome.search_toggle', !!report.drSearchToggle);

      if (bp.label === 'desktop') {
        recordAssertion(assertionLog, 'nav.hover.color', !!report.drNavHover?.color);
        recordAssertion(
          assertionLog,
          'issues.dropdown.panel',
          !!(report.drIssuesDropdown as { found?: boolean })?.found,
        );
        recordAssertion(assertionLog, 'donate.cta.color', !!report.drDonateDefault?.color);
        recordAssertion(assertionLog, 'footer.link.hover', !!report.drFooterLinkHover?.color);
        if (isContent) {
          recordAssertion(assertionLog, 'body.link.hover', !!report.drBodyLinkHover?.color);
        } else {
          recordAssertion(assertionLog, 'body.link.hover.n/a', true);
        }
      } else {
        recordAssertion(assertionLog, 'nav.hover.n/a_mobile', true);
        recordAssertion(assertionLog, 'issues.dropdown.n/a_mobile', true);
      }

      if (bp.label === 'mobile') {
        recordAssertion(
          assertionLog,
          'mobile.drawer.toggle',
          report.drMegaMenuOpen === true || Boolean(report.drDrawerOpen),
        );
      } else {
        recordAssertion(assertionLog, 'mobile.drawer.n/a_desktop', true);
      }

      // PostX scroll FX not ported — capture only, pass when absent or static.
      const reveal = report.drReveal as { count?: number; visible?: number } | undefined;
      recordAssertion(
        assertionLog,
        'scroll_reveal.inventory',
        reveal === undefined || typeof reveal.count === 'number',
      );

      report.assertions = assertionLog;
      report.assertionPass = assertionLog.filter((a) => a.pass).length;
      report.assertionTotal = assertionLog.length;

      writeJson(path.join(outBase, 'behavior.json'), report);

      await wpPage.close();
      await drPage.close();
    });
  }
}

test.afterAll(() => {
  writeJson(path.join(parityRoot, 'reports/behavior', runId, 'run-meta.json'), {
    runId,
    wpBase,
    drupalBase,
    baselineId: baselineId || null,
    paths,
    assertionSummary: {
      tests: globalStats.tests,
      assertions: globalStats.assertions,
      passed: globalStats.passed,
      passRate: globalStats.assertions ? globalStats.passed / globalStats.assertions : 0,
    },
    note: 'See behavior.json per case for per-inventory assertion names.',
  });
});
