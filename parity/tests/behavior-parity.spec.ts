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
import { buildCrossSiteReport } from '../lib/behavior-compare.mjs';
import {
  captureReducedMotionHero,
  captureSiteBehavior,
  DRUPAL_PROFILE,
  WP_PROFILE,
} from '../lib/behavior-profiles.mjs';
import {
  expectedBehaviorCaseCount,
  loadTemplateSamplePaths,
  normalizeBehaviorPath,
  pathUsesChromeHeader,
} from '../lib/behavior-paths.mjs';
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
type WpSnap = Record<string, unknown> | null;

function writeJson(file: string, data: unknown) {
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, JSON.stringify(data, null, 2));
}

const globalStats = {
  tests: 0,
  assertions: 0,
  passed: 0,
  crossMismatches: 0,
  capturedCases: 0,
  incompleteCases: 0,
};

function recordAssertion(stats: AssertionStat[], name: string, pass: boolean) {
  stats.push({ name, pass });
  globalStats.assertions += 1;
  if (pass) globalStats.passed += 1;
  if (process.env.PARITY_BEHAVIOR_STRICT === '1' || crossSite) {
    expect.soft(pass, name).toBeTruthy();
  }
}

function wpSnapshotReady(pathNorm: string, snap: Record<string, unknown> | null) {
  if (!snap) return false;
  const isHome = pathNorm === '/' || pathNorm === '/homepage';
  if (isHome && !(snap.hero as { found?: boolean } | undefined)?.found) return false;
  const layout600 = (snap.headerScrollDepths as { y600?: { found?: boolean } } | undefined)?.y600;
  const hasChrome =
    Boolean(layout600?.found) ||
    Boolean(snap.navDefault) ||
    Boolean((snap.headerBeforeScroll as { found?: boolean } | undefined)?.found);
  return hasChrome;
}

function drupalUrl(pathNorm: string) {
  if (pathNorm === '/homepage') return `${drupalBase}/homepage`;
  return `${drupalBase}${pathNorm.startsWith('/') ? pathNorm : `/${pathNorm}`}`;
}

function wpUrl(pathNorm: string) {
  const p = pathNorm === '/homepage' ? '/' : pathNorm.startsWith('/') ? pathNorm : `/${pathNorm}`;
  return `${wpBase}${p}`;
}

async function headerScrollScreenshots(page: import('@playwright/test').Page, outBase: string, prefix: string) {
  for (const scrollY of [600, 2000]) {
    await page.evaluate((y) => window.scrollTo(0, y), scrollY);
    await page.waitForTimeout(450);
    await page.screenshot({
      path: path.join(outBase, `${prefix}-header-scroll-${scrollY}.png`),
      fullPage: false,
    });
  }
}

async function loadWpSnapshot(
  wpPage: import('@playwright/test').Page,
  pathNorm: string,
  bp: (typeof BEHAVIOR_BREAKPOINTS)[number],
  report: Record<string, unknown>,
): Promise<WpSnap> {
  const target = wpUrl(pathNorm);
  const maxAttempts = bp.label === 'desktop' ? 5 : 8;
  const gotoTimeout = bp.label === 'desktop' ? 120_000 : 180_000;
  let lastErr: unknown;
  let snap: WpSnap = null;

  for (let attempt = 0; attempt < maxAttempts; attempt += 1) {
    try {
      if (attempt > 0) {
        const backoff = Math.min(32_000, 4000 * 2 ** (attempt - 1));
        await wpPage.waitForTimeout(backoff);
      }
      await wpPage.goto(target, { waitUntil: 'domcontentloaded', timeout: gotoTimeout });
      await dismissOverlays(wpPage);
      await waitForFonts(wpPage);
      if (pathNorm === '/homepage' || pathNorm === '/') {
        await wpPage.waitForSelector(WP_PROFILE.heroVideo, { timeout: 45_000 }).catch(() => {});
      }
      await wpPage
        .waitForSelector(`${WP_PROFILE.selectors.header}, ${WP_PROFILE.selectors.navLink}`, {
          timeout: 45_000,
        })
        .catch(() => {});
      await wpPage.waitForTimeout(900);
      snap = await captureSiteBehavior(wpPage, WP_PROFILE, bp);
      if (wpSnapshotReady(pathNorm, snap)) {
        report.wp = snap;
        return snap;
      }
      snap = null;
      report.wpError = `wpSnapshotReady=false attempt ${attempt + 1}/${maxAttempts}`;
    } catch (e) {
      lastErr = e;
      report.wpError = String(e);
      snap = null;
    }
  }
  if (lastErr) {
    report.wpError = String(lastErr);
  }
  return null;
}

test.describe.configure({ mode: 'serial', retries: 0 });

for (const pathNorm of paths) {
  for (const bp of BEHAVIOR_BREAKPOINTS) {
    test(`behavior ${pathNorm} @ ${bp.label}`, async ({ browser, browserName }) => {
      test.skip(browserName !== 'chromium', 'Chromium only');
      if (crossSite) {
        test.setTimeout(bp.label === 'desktop' ? 300_000 : 420_000);
      }
      globalStats.tests += 1;

      const assertionLog: AssertionStat[] = [];
      const slug = pathNorm.replace(/\//g, '_') || 'root';
      const outBase = behaviorOutDir(parityRoot, runId, 'compare', `${slug}_${bp.label}`);
      const report: Record<string, unknown> = {
        path: pathNorm,
        breakpoint: bp.label,
        runId,
        crossSite,
        hitWp,
        captured: false,
      };

      const wpPage = await browser.newPage();
      const drPage = await browser.newPage();
      await wpPage.setViewportSize({ width: bp.width, height: bp.height });
      await drPage.setViewportSize({ width: bp.width, height: bp.height });
      await prepareBehaviorPage(wpPage);
      await prepareBehaviorPage(drPage);

      let wpSnap: WpSnap = null;
      if (hitWp) {
        wpSnap = await loadWpSnapshot(wpPage, pathNorm, bp, report);
        if (wpSnap && pathUsesChromeHeader(pathNorm)) {
          await headerScrollScreenshots(wpPage, outBase, 'wp');
        }
      }

      const isHome = pathNorm === '/' || pathNorm === '/homepage';

      await drPage.goto(drupalUrl(pathNorm), { waitUntil: 'domcontentloaded', timeout: 90_000 });
      await dismissOverlays(drPage);
      await waitForFonts(drPage);
      await drPage.waitForTimeout(600);
      const drSnap = await captureSiteBehavior(drPage, DRUPAL_PROFILE, bp);
      report.dr = drSnap;
      await drPage.screenshot({ path: path.join(outBase, 'drupal-scroll.png'), fullPage: false });
      if (pathUsesChromeHeader(pathNorm)) {
        await headerScrollScreenshots(drPage, outBase, 'drupal');
      }

      if (bp.label === 'mobile' && drSnap.mobileMenu?.opened) {
        await drPage.screenshot({ path: path.join(outBase, 'drupal-drawer-open.png'), fullPage: false });
      }

      if (crossSite && wpSnap && drSnap && wpSnapshotReady(pathNorm, wpSnap)) {
        const cross = buildCrossSiteReport(pathNorm, bp, wpSnap, drSnap);
        report.crossSite = cross;
        report.captured = true;
        globalStats.capturedCases += 1;
        if (!cross.match) globalStats.crossMismatches += 1;
        writeJson(path.join(outBase, 'wp-drupal-behavior-diff.json'), cross);

        recordAssertion(assertionLog, 'cross.diff.zero_mismatch_rows', cross.match);
      } else if (crossSite) {
        globalStats.incompleteCases += 1;
        recordAssertion(assertionLog, 'cross.wp.snapshot', false);
      } else if (drSnap) {
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
  const expectedCases = expectedBehaviorCaseCount(paths);
  writeJson(path.join(runDir, 'run-meta.json'), {
    runId,
    wpBase,
    drupalBase,
    baselineId: baselineId || null,
    paths,
    expectedCases,
    crossSite,
    hitWp,
    assertionSummary: {
      tests: globalStats.tests,
      assertions: globalStats.assertions,
      passed: globalStats.passed,
      passRate: globalStats.assertions ? globalStats.passed / globalStats.assertions : 0,
    },
    crossSiteMismatchCases: globalStats.crossMismatches,
    capturedCases: globalStats.capturedCases,
    incompleteCases: globalStats.incompleteCases,
    note: 'Per-case wp-drupal-behavior-diff.json when crossSite; aggregate via npm run parity:behavior:report-diff',
  });
});
