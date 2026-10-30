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

  test('QA-045 mobile header inset @ 768px matches WP outer spacing', async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 900 });
    await page.goto(`${drupalBase}/`, { waitUntil: 'load' });
    await dismissOverlays(page);

    const box = await page.locator('.mobile-header .impact-header').first().boundingBox();
    // WP: max(1.75rem, 5vw) = 38.4px at 768.
    expect(Math.abs((box?.x ?? 0) - 38.4)).toBeLessThanOrEqual(2);
    expect(Math.abs((box?.width ?? 0) - (768 - 2 * 38.4))).toBeLessThanOrEqual(2);
    const donate = await page.locator('.mobile-header .header-button-section a').first().boundingBox();
    expect((donate?.x ?? 0) + (donate?.width ?? 0)).toBeLessThanOrEqual(737);
  });

  test('QA-045 Issues link and search row widths match WP @ 390px', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 900 });
    await page.goto(`${drupalBase}/`, { waitUntil: 'load' });
    await dismissOverlays(page);
    await page.evaluate(() => document.fonts.ready);

    // Live: blockmag static Montserrat SemiBold + 6px indicator gap = 117px; the row (link + 25px
    // search) is within ~1px of the column, so it wraps under platform font rendering like WP.
    const issues = page.locator('.mobile-header #mega-menu-max_mega_menu_1 > li.mega-menu-item-599 > a').first();
    expect(Math.round((await issues.boundingBox())?.width ?? 0)).toBe(117);
    expect(
      await page.evaluate(() =>
        [...document.styleSheets].some((s) => (s.href || '').includes('fonts.googleapis.com_css')),
      ),
    ).toBe(false);
  });

  for (const path of ['/', '/about', '/fall-2026-issue', '/category/cancer-research', '/?s=cancer&id=701']) {
    test(`QA-050 no horizontal overflow @ 390px ${path}`, async ({ page }) => {
      await page.setViewportSize({ width: 390, height: 900 });
      await page.goto(`${drupalBase}${path}`, { waitUntil: 'load' });
      expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBe(390);
    });
  }

  test('QA-046 search result image fills the column @ 768px like WP (691x496)', async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 900 });
    await page.goto(`${drupalBase}/?s=cancer&id=701`, { waitUntil: 'load' });
    const img = page.locator('main li.wp-block-post .wp-block-post-featured-image img').first();
    await expect(img).toBeVisible();
    await expect.poll(async () => Math.round((await img.boundingBox())?.width ?? 0)).toBe(691);
    expect(Math.abs(((await img.boundingBox())?.height ?? 0) - 496)).toBeLessThanOrEqual(2);
  });

  test('QA-051 search popup input is pre-filled with the current query', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto(`${drupalBase}/?s=cancer&id=701`, { waitUntil: 'load' });
    await dismissOverlays(page);
    await page.locator('.desktop-header .header-search a').first().click();
    const input = page.locator('#is-search-input-701');
    await expect(input).toBeVisible();
    await expect(input).toHaveValue('cancer');
    await page.goto(`${drupalBase}/`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#is-search-input-701')).toHaveValue('');
  });

  test('QA-045 opened Issues submenu pushes content down @ 390px', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 900 });
    await page.goto(`${drupalBase}/`, { waitUntil: 'load' });
    await dismissOverlays(page);

    const header = page.locator('.mobile-header .impact-header').first();
    const closedHeight = (await header.boundingBox())?.height ?? 0;
    const issues = page.locator('.mobile-header #mega-menu-max_mega_menu_1 > li.mega-menu-item-599 > a').first();
    await issues.click();
    const submenu = page.locator('.mobile-header #mega-menu-max_mega_menu_1 > li.mega-menu-item-599 > ul.mega-sub-menu').first();
    await expect(submenu).toBeVisible();
    // Max Mega Menu slides the flyout open with jQuery (400ms).
    await expect.poll(async () => (await header.boundingBox())?.height ?? 0).toBeGreaterThan(closedHeight + 100);

    const headerBox = await header.boundingBox();
    const subBox = await submenu.boundingBox();
    const searchBox = await page.locator('.mobile-header .header-search a').first().boundingBox();
    const mainBox = await page.locator('main#wp--skip-link--target').first().boundingBox();
    expect(headerBox?.height ?? 0).toBeGreaterThan(closedHeight + 100);
    expect(searchBox?.y ?? 0).toBeGreaterThanOrEqual((subBox?.y ?? 0) + (subBox?.height ?? 0) - 1);
    expect(mainBox?.y ?? 0).toBeGreaterThanOrEqual((headerBox?.y ?? 0) + (headerBox?.height ?? 0) - 1);
  });

  test('QA-047 popup search submits like WP (/?s=term&id=701)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto(`${drupalBase}/`, { waitUntil: 'load' });
    await dismissOverlays(page);

    await page.locator('.desktop-header .header-search a').first().click();
    const input = page.locator('#is-search-input-701');
    await expect(input).toBeVisible();
    await input.fill('cancer');
    const [response] = await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      input.press('Enter'),
    ]);
    expect(response?.status()).toBe(200);
    const url = new URL(page.url());
    expect(url.pathname).toBe('/');
    expect(url.searchParams.get('s')).toBe('cancer');
    expect(url.searchParams.get('id')).toBe('701');
    await expect(page.locator('main li.wp-block-post').first()).toBeVisible();
    await expect(page.locator('.wp-block-query-pagination a[href^="/page/2/?"]').first()).toBeAttached();

    const legacy = await page.request.get(`${drupalBase}/search?s=cancer`);
    expect(legacy.status()).toBe(200);
    expect(await legacy.text()).toContain('bsearch_highlight');
  });

  test('QA-046 search results layout matches WP Better Search rows @ 1280px', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto(`${drupalBase}/?s=cancer&id=701`, { waitUntil: 'load' });
    await dismissOverlays(page);

    const row = page.locator('main li.wp-block-post').first();
    await expect(row).toBeVisible();
    await expect(page.locator('main .card__topics, main .article__tag')).toHaveCount(0);
    await expect(row.locator('.wp-block-post-title mark.bsearch_highlight').first()).toBeVisible();

    const img = await row.locator('.wp-block-post-featured-image img').first().boundingBox();
    const title = await row.locator('.wp-block-post-title a').first().boundingBox();
    expect(title?.x ?? 0).toBeGreaterThan((img?.x ?? 0) + (img?.width ?? 0));
    expect(Math.abs((title?.y ?? 0) - (img?.y ?? 0))).toBeLessThanOrEqual(4);

    const style = await row.locator('.wp-block-post-title a').first().evaluate((el) => {
      const cs = getComputedStyle(el);
      return { color: cs.color, family: cs.fontFamily, size: cs.fontSize, weight: cs.fontWeight };
    });
    expect(style).toEqual({ color: 'rgb(17, 17, 17)', family: '"Libre Baskerville"', size: '24px', weight: '600' });
    expect(await page.evaluate(() => document.fonts.check('600 24px "Libre Baskerville"'))).toBe(true);
  });

  test('QA-048 Issues nav link and submenu stay translatable like WP', async ({ page }) => {
    await page.goto(`${drupalBase}/`, { waitUntil: 'domcontentloaded' });
    const links = page.locator('#mega-menu-max_mega_menu_1 > li.mega-menu-item-599 a.mega-menu-link');
    expect(await links.count()).toBeGreaterThan(2);
    for (const link of await links.all()) {
      const blocked = await link.evaluate((el) => !!el.closest('.notranslate, [translate="no"]'));
      expect(blocked).toBe(false);
    }
  });
});
