/**
 * QA pass 1 header chrome regressions (QA-013 mobile nav, QA-014 search overlay).
 */
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { dismissOverlays, preparePage } from '../lib/page-utils.mjs';

const parityRoot = process.cwd();
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const wpBase = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');
const useWpCache = process.env.PARITY_ISSUE_WP_CACHE_ONLY === '1';

test.describe('header chrome QA', () => {
  test.beforeEach(async ({ page }) => {
    await preparePage(page);
  });

  for (const width of [768, 390]) {
    test(`QA-013 mobile header shows Issues and search @ ${width}px`, async ({ page }) => {
      await page.setViewportSize({ width, height: 900 });
      await page.goto(`${drupalBase}/`, { waitUntil: 'domcontentloaded' });
      await dismissOverlays(page);

      const header = page.locator('.mobile-header').first();
      await expect(header).toBeVisible();
      const issuesLink = header.locator('#mega-menu-max_mega_menu_1 a.mega-menu-link', { hasText: 'Issues' }).first();
      await expect(issuesLink).toBeVisible();
      const search = header.locator('.header-search a').first();
      await expect(search).toBeVisible();
      const donate = header.locator('.header-button-section a').first();
      await expect(donate).toBeVisible();

      const issuesBox = await issuesLink.boundingBox();
      const searchBox = await search.boundingBox();
      expect(issuesBox?.width ?? 0).toBeGreaterThan(10);
      expect(searchBox?.width ?? 0).toBeGreaterThan(10);
    });
  }

  test('QA-014 desktop search opens overlay @ 1280px', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto(`${drupalBase}/`, { waitUntil: 'domcontentloaded' });
    await dismissOverlays(page);

    const searchIcon = page.locator('.desktop-header .header-search a').first();
    await expect(searchIcon).toBeVisible();
    await searchIcon.click();

    const popup = page.locator('#is-popup-wrapper');
    await expect(popup).toBeVisible();
    const input = page.locator('#is-search-input-701');
    await expect(input).toBeVisible();
    await expect(input).toBeFocused();

    const url = page.url();
    expect(url).not.toMatch(/#$/);
  });

  test('QA-014 search overlay matches WP behavior (cached or live)', async ({ page }) => {
    test.skip(!useWpCache && !process.env.PARITY_HEADER_QA_LIVE_WP, 'Set PARITY_ISSUE_WP_CACHE_ONLY=1 or PARITY_HEADER_QA_LIVE_WP=1');

    await page.setViewportSize({ width: 1280, height: 900 });
    const refUrl = useWpCache
      ? `file://${path.join(parityRoot, 'reference-pack', 'homepage', 'desktop', 'rendered.html')}`
      : `${wpBase}/`;
    await page.goto(refUrl, { waitUntil: 'domcontentloaded', timeout: 120_000 });
    if (!useWpCache) {
      await dismissOverlays(page);
    }

    if (useWpCache) {
      await expect(page.locator('#is-popup-wrapper')).toHaveCount(1);
      await expect(page.locator('#is-search-input-701')).toHaveCount(1);
      await expect(page.locator('.header-search.is-menu.popup').first()).toBeAttached();
      return;
    }

    const refSearch = page.locator('.desktop-header .header-search a, .mobile-header .header-search a').first();
    await refSearch.click();
    await expect(page.locator('#is-popup-wrapper')).toBeVisible();
    await expect(page.locator('#is-search-input-701')).toBeVisible();
  });
});
