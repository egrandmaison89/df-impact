/**
 * Dynamic behavior parity: hover, scroll, hero video, drawer (live WP + Drupal).
 * Run on owner Mac: npm run parity:behavior
 * Baseline WP reference: PARITY_WP_BASELINE=<id> (reads wordpress.json hero slice if present).
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
import { resolveParityRoot } from '../lib/parity-root.mjs';
import { dismissOverlays, waitForFonts } from '../lib/page-utils.mjs';

const parityRoot = resolveParityRoot();
const wpBase = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const runId = process.env.PARITY_BEHAVIOR_RUN_ID || new Date().toISOString().replace(/[:.]/g, '-');
const recordVideo = process.env.PARITY_BEHAVIOR_VIDEO === '1';
const baselineId = process.env.PARITY_WP_BASELINE || '';

const paths = (process.env.PARITY_BEHAVIOR_PATHS || '/homepage,/')
  .split(',')
  .map((p) => p.trim())
  .filter(Boolean);

function writeJson(file: string, data: unknown) {
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, JSON.stringify(data, null, 2));
}

test.describe.configure({ mode: 'serial' });

for (const pathNorm of paths) {
  for (const bp of BEHAVIOR_BREAKPOINTS) {
    test(`behavior ${pathNorm} @ ${bp.label}`, async ({ browser, browserName }, testInfo) => {
      test.skip(browserName !== 'chromium', 'Chromium only');

      const slug = pathNorm.replace(/\//g, '_') || 'root';
      const outBase = behaviorOutDir(parityRoot, runId, 'compare', `${slug}_${bp.label}`);
      const report: Record<string, unknown> = { path: pathNorm, breakpoint: bp.label, runId };

      const wpUrl = `${wpBase}${pathNorm.startsWith('/') ? pathNorm : `/${pathNorm}`}`;
      const drUrl = `${drupalBase}${pathNorm === '/homepage' ? '/homepage' : pathNorm.startsWith('/') ? pathNorm : `/${pathNorm}`}`;

      const wpPage = await browser.newPage();
      const drPage = await browser.newPage();
      if (recordVideo) {
        await wpPage.video()?.path(); // ensure context records if configured at project level
      }
      await wpPage.setViewportSize({ width: bp.width, height: bp.height });
      await drPage.setViewportSize({ width: bp.width, height: bp.height });

      await prepareBehaviorPage(wpPage);
      await prepareBehaviorPage(drPage);

      // --- WordPress (skip live if baseline-only mode without network) ---
      let wpHero = null;
      if (!baselineId || process.env.PARITY_BEHAVIOR_HIT_WP === '1') {
        try {
          await wpPage.goto(wpUrl, { waitUntil: 'domcontentloaded' });
          await dismissOverlays(wpPage);
          await waitForFonts(wpPage);
          await wpPage.waitForTimeout(500);
          wpHero = await videoState(wpPage, WP_HERO_VIDEO);
          report.wpHero = wpHero;
          report.wpNavDefault = await computedStyle(wpPage, '.header__nav-link, .mega-menu-link, header nav a');
          report.wpNavHover = await computedStyleHover(wpPage, '.header__nav-link, .mega-menu-link, header nav a');
          await wpPage.evaluate(() => window.scrollTo(0, 120));
          await wpPage.waitForTimeout(200);
          await wpPage.screenshot({ path: path.join(outBase, 'wp-scroll.png'), fullPage: false });
          report.wpHeaderScroll = await computedStyle(wpPage, 'header, .desktop-header, .impact-header');
        } catch (e) {
          report.wpError = String(e);
        }
      }

      // --- Drupal ---
      await drPage.goto(drUrl, { waitUntil: 'domcontentloaded' });
      await waitForFonts(drPage);
      await drPage.waitForTimeout(500);
      const drHero = await videoState(drPage, DRUPAL_HERO_VIDEO);
      report.drHero = drHero;
      report.drNavDefault = await computedStyle(drPage, '.header__nav-link');
      report.drNavHover = await computedStyleHover(drPage, '.header__nav-link');
      report.drHeaderBeforeScroll = await headerScrollState(drPage, '.header');
      report.drDonateDefault = await computedStyle(drPage, '.header__donate, .homepage-banner-button a');
      report.drDonateHover = await computedStyleHover(drPage, '.header__donate, .homepage-banner-button a');
      report.drIssuesDropdown = await dropdownOpenOnHover(
        drPage,
        '.header__nav-item--issues, .mega-menu-item-has-children',
        '.header__dropdown, .mega-sub-menu',
      );
      report.drGtranslate = await elementPresent(drPage, '.gtranslate_wrapper, #gtranslate_selector');
      report.drSearchToggle = await elementPresent(drPage, '.header__search-link, .header-search a, .is-search-icon');
      report.drReveal = await revealState(drPage);
      await drPage.evaluate(() => window.scrollTo(0, 120));
      await drPage.waitForTimeout(200);
      await drPage.screenshot({ path: path.join(outBase, 'drupal-scroll.png'), fullPage: false });
      report.drHeaderScroll = await computedStyle(drPage, '.header');
      report.drHeaderAfterScroll = await headerScrollState(drPage, '.header');

      if (bp.label === 'mobile') {
        const toggle = drPage.locator('.header__menu-toggle');
        if (await toggle.isVisible()) {
          await toggle.click();
          await drPage.waitForTimeout(250);
          await drPage.screenshot({ path: path.join(outBase, 'drupal-drawer-open.png') });
          report.drDrawerOpen = await computedStyle(drPage, '.header__drawer');
          await toggle.click();
        }
      }

      // Reduced motion hero (Drupal)
      await prepareBehaviorPage(drPage, { reducedMotion: true });
      await drPage.reload({ waitUntil: 'domcontentloaded' });
      await drPage.waitForTimeout(400);
      report.drHeroReducedMotion = await videoState(drPage, DRUPAL_HERO_VIDEO);

      writeJson(path.join(outBase, 'behavior.json'), report);

      const isHome = pathNorm === '/' || pathNorm === '/homepage';
      if (isHome && wpHero?.found) {
        expect(drHero?.found, 'Drupal hero video element').toBeTruthy();
        expect(drHero?.muted).toBe(true);
        expect(drHero?.loop).toBe(true);
        expect(drHero?.playsInline).toBe(true);
        expect(drHero?.autoplay).toBe(true);
        if (process.env.PARITY_BEHAVIOR_STRICT === '1') {
          expect(drHero?.currentTimeAdvanced, 'video should advance when not reduced-motion').toBe(true);
        }
      }

      if (isHome) {
        expect(report.drGtranslate, 'GTranslate widget mount point').toBeTruthy();
        expect(report.drSearchToggle, 'Header search control').toBeTruthy();
      }

      expect(report.drHeaderBeforeScroll?.found, 'Header element').toBeTruthy();
      expect(report.drHeaderAfterScroll?.scrolled, 'Header scrolled class after scrollY=120').toBe(true);

      if (bp.label === 'desktop') {
        expect(report.drIssuesDropdown?.found, 'Issues dropdown panel').toBeTruthy();
      }

      if (report.drHeroReducedMotion?.found) {
        expect(
          report.drHeroReducedMotion.paused || !report.drHeroReducedMotion.currentTimeAdvanced,
          'Hero respects reduced motion',
        ).toBeTruthy();
      }

      expect(report.drNavHover?.color, 'Nav hover color computed').toBeTruthy();

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
    note: 'See behavior.json per case; set PARITY_BEHAVIOR_STRICT=1 to fail on video playback.',
  });
});
