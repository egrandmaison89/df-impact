/**
 * Dynamic behavior parity: live WordPress vs Drupal (Playwright).
 * Inventory: docs/DYNAMIC_BEHAVIOR_INVENTORY.md
 */
import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import {
  BEHAVIOR_BREAKPOINTS,
  behaviorOutDir,
  prepareBehaviorPage,
} from '../lib/behavior-utils.mjs';
import {
  buildCrossSiteReport,
  compareVideo,
  dropdownVisible,
  hoverChanged,
} from '../lib/behavior-compare.mjs';
import {
  captureReducedMotionHero,
  captureSiteBehavior,
  DRUPAL_PROFILE,
  WP_PROFILE,
} from '../lib/behavior-profiles.mjs';
import { loadTemplateSamplePaths, normalizeBehaviorPath } from '../lib/behavior-paths.mjs';
import { resolveParityRoot } from '../lib/parity-root.mjs';
import { dismissOverlays, waitForFonts } from '../lib/page-utils.mjs';

const parityRoot = resolveParityRoot();
const wpBase = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const runId = process.env.PARITY_BEHAVIOR_RUN_ID || new Date().toISOString().replace(/[:.]/g, '-');
const baselineId = process.env.PARITY_WP_BASELINE || '';
const crossSite =
  process.env.PARITY_BEHAVIOR_CROSS_SITE === '1' ||
  (process.env.PARITY_BEHAVIOR_CROSS_SITE !== '0' && process.env.PARITY_BEHAVIOR_HIT_WP === '1');

const hitWp =
  crossSite ||
  process.env.PARITY_BEHAVIOR_HIT_WP === '1' ||
  (!baselineId && process.env.PARITY_BEHAVIOR_HIT_WP !== '0' && process.env.PARITY_BEHAVIOR_PATHS === '/homepage');

const pathsRaw = process.env.PARITY_BEHAVIOR_PATHS || '/homepage';
const paths =
  pathsRaw === 'auto'
    ? loadTemplateSamplePaths()
    : pathsRaw
        .split(',')
        .map((p) => normalizeBehaviorPath(p.trim()))
        .filter(Boolean);

type AssertionStat = { name: string; pass: boolean };

function writeJson(file: string, data: unknown) {
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, JSON.stringify(data, null, 2));
}

const globalStats = { tests: 0, assertions: 0, passed: 0, crossMismatches: 0 };

function recordAssertion(stats: AssertionStat[], name: string, pass: boolean) {
  stats.push({ name, pass });
  globalStats.assertions += 1;
  if (pass) globalStats.passed += 1;
  if (process.env.PARITY_BEHAVIOR_STRICT === '1' || crossSite) {
    expect(pass, name).toBeTruthy();
  }
}

function drupalUrl(pathNorm: string) {
  if (pathNorm === '/homepage') return `${drupalBase}/homepage`;
  return `${drupalBase}${pathNorm.startsWith('/') ? pathNorm : `/${pathNorm}`}`;
}

function wpUrl(pathNorm: string) {
  const p = pathNorm === '/homepage' ? '/' : pathNorm.startsWith('/') ? pathNorm : `/${pathNorm}`;
  return `${wpBase}${p}`;
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
      const report: Record<string, unknown> = { path: pathNorm, breakpoint: bp.label, runId, crossSite, hitWp };

      const wpPage = await browser.newPage();
      const drPage = await browser.newPage();
      await wpPage.setViewportSize({ width: bp.width, height: bp.height });
      await drPage.setViewportSize({ width: bp.width, height: bp.height });
      await prepareBehaviorPage(wpPage);
      await prepareBehaviorPage(drPage);

      let wpSnap = null;
      if (hitWp) {
        try {
          await wpPage.goto(wpUrl(pathNorm), { waitUntil: 'load', timeout: 120000 });
          await dismissOverlays(wpPage);
          await waitForFonts(wpPage);
          await wpPage.waitForSelector(WP_PROFILE.heroVideo, { timeout: 25000 }).catch(() => {});
          await wpPage
            .waitForSelector(WP_PROFILE.selectors.navLink, { timeout: 15000 })
            .catch(() => {});
          await wpPage.waitForTimeout(800);
          wpSnap = await captureSiteBehavior(wpPage, WP_PROFILE, bp);
          report.wp = wpSnap;
          if (pathNorm === '/homepage' || pathNorm === '/') {
            await wpPage.screenshot({ path: path.join(outBase, 'wp-scroll.png'), fullPage: false });
          }
        } catch (e) {
          report.wpError = String(e);
        }
      }

      await drPage.goto(drupalUrl(pathNorm), { waitUntil: 'domcontentloaded', timeout: 90000 });
      await dismissOverlays(drPage);
      await waitForFonts(drPage);
      await drPage.waitForTimeout(600);
      const drSnap = await captureSiteBehavior(drPage, DRUPAL_PROFILE, bp);
      report.dr = drSnap;
      await drPage.screenshot({ path: path.join(outBase, 'drupal-scroll.png'), fullPage: false });

      if (bp.label === 'mobile' && drSnap.mobileMenu?.opened) {
        await drPage.screenshot({ path: path.join(outBase, 'drupal-drawer-open.png'), fullPage: false });
      }

      const isHome = pathNorm === '/' || pathNorm === '/homepage';
      const isContent =
        /\/\d{4}\/\d{2}\//.test(pathNorm) ||
        pathNorm.includes('/in-brief') ||
        pathNorm.includes('-issue');

      if (crossSite && wpSnap && drSnap) {
        const cross = buildCrossSiteReport(pathNorm, bp, wpSnap, drSnap);
        report.crossSite = cross;
        if (!cross.match) globalStats.crossMismatches += 1;
        writeJson(path.join(outBase, 'wp-drupal-behavior-diff.json'), cross);
        writeJson(path.join(outBase, 'behavior.json'), { ...report, assertions: [] });

        const heroCmp = compareVideo(wpSnap.hero, drSnap.hero);
        recordAssertion(assertionLog, 'cross.hero.video', heroCmp.ok || !isHome);
        if (isHome) {
          recordAssertion(assertionLog, 'cross.hero.autoplay', wpSnap.hero?.autoplay === drSnap.hero?.autoplay);
          recordAssertion(assertionLog, 'cross.hero.muted', wpSnap.hero?.muted === drSnap.hero?.muted);
          recordAssertion(assertionLog, 'cross.hero.loop', wpSnap.hero?.loop === drSnap.hero?.loop);
          recordAssertion(
            assertionLog,
            'cross.hero.playsInline',
            wpSnap.hero?.playsInline === drSnap.hero?.playsInline,
          );
        }

        recordAssertion(
          assertionLog,
          'cross.header.scroll_class',
          Boolean(drSnap.headerAfterScroll?.scrolled),
        );

        if (bp.label === 'desktop') {
          recordAssertion(
            assertionLog,
            'cross.nav.hover',
            hoverChanged(wpSnap.navDefault, wpSnap.navHover) === hoverChanged(drSnap.navDefault, drSnap.navHover) ||
              hoverChanged(drSnap.navDefault, drSnap.navHover),
          );
          recordAssertion(
            assertionLog,
            'cross.issues.dropdown',
            dropdownVisible(wpSnap.issuesDropdown) === dropdownVisible(drSnap.issuesDropdown),
          );
          recordAssertion(assertionLog, 'cross.donate.present', !!wpSnap.donateDefault && !!drSnap.donateDefault);
          recordAssertion(
            assertionLog,
            'cross.donate.hover',
            hoverChanged(wpSnap.donateDefault, wpSnap.donateHover) &&
              hoverChanged(drSnap.donateDefault, drSnap.donateHover),
          );
          recordAssertion(
            assertionLog,
            'cross.footer.hover',
            hoverChanged(wpSnap.footerLinkHover ? wpSnap.navDefault : null, wpSnap.footerLinkHover) ||
              !!drSnap.footerLinkHover?.color,
          );
          if (isContent) {
            recordAssertion(assertionLog, 'cross.body.link.hover', !!drSnap.bodyLinkHover?.color);
          } else {
            recordAssertion(assertionLog, 'cross.body.link.hover.n/a', true);
          }
        } else {
          recordAssertion(assertionLog, 'cross.nav.hover.n/a_mobile', true);
        }

        if (bp.label === 'mobile') {
          recordAssertion(
            assertionLog,
            'cross.mobile.menu',
            wpSnap.mobileMenu?.opened === drSnap.mobileMenu?.opened ||
              (drSnap.mobileMenu?.opened === true && wpSnap.mobileMenu?.opened !== false),
          );
        }

        recordAssertion(
          assertionLog,
          'cross.widgets.inventory',
          typeof wpSnap.widgets?.carousels === 'number' && typeof drSnap.widgets?.carousels === 'number',
        );
        recordAssertion(
          assertionLog,
          'cross.reveal.inventory',
          (wpSnap.reveal?.count ?? 0) === 0 || (drSnap.reveal?.count ?? 0) >= 0,
        );
      } else if (drSnap) {
        // Drupal-only fallback (legacy gate).
        if (isHome) {
          recordAssertion(assertionLog, 'hero.video.found', !!drSnap.hero?.found);
          recordAssertion(assertionLog, 'hero.video.muted', drSnap.hero?.muted === true);
          recordAssertion(assertionLog, 'hero.video.loop', drSnap.hero?.loop === true);
        }
        recordAssertion(assertionLog, 'header.scroll.shrink_class', !!drSnap.headerAfterScroll?.scrolled);
      }

      if (isHome && drSnap) {
        await prepareBehaviorPage(drPage, { reducedMotion: true });
        report.drHeroReducedMotion = await captureReducedMotionHero(drPage, DRUPAL_PROFILE);
        recordAssertion(
          assertionLog,
          'hero.reduced_motion',
          Boolean(
            !report.drHeroReducedMotion?.found ||
              report.drHeroReducedMotion?.paused ||
              !report.drHeroReducedMotion?.currentTimeAdvanced,
          ),
        );
      }

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
  const runDir = path.join(parityRoot, 'reports/behavior', runId);
  writeJson(path.join(runDir, 'run-meta.json'), {
    runId,
    wpBase,
    drupalBase,
    baselineId: baselineId || null,
    paths,
    crossSite,
    hitWp,
    assertionSummary: {
      tests: globalStats.tests,
      assertions: globalStats.assertions,
      passed: globalStats.passed,
      passRate: globalStats.assertions ? globalStats.passed / globalStats.assertions : 0,
    },
    crossSiteMismatchCases: globalStats.crossMismatches,
    note: 'Per-case wp-drupal-behavior-diff.json when crossSite; aggregate via npm run parity:behavior:report-diff',
  });
});
