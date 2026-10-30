/**
 * QA pass 1 listings, pagers and article templates. Expected values were measured on live WP.
 */
import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { dismissOverlays, preparePage } from '../lib/page-utils.mjs';

const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');

async function open(page: Page, path: string, width = 1280) {
  await page.setViewportSize({ width, height: 900 });
  const response = await page.goto(`${drupalBase}${path}`, { waitUntil: 'load' });
  await dismissOverlays(page);
  return response;
}

async function pagerSummary(page: Page) {
  return page.evaluate(() => {
    const nav = document.querySelector('nav.wp-block-query-pagination');
    if (!nav) return null;
    const items = [...nav.querySelectorAll('.wp-block-query-pagination-numbers > *')].map((el) =>
      el.classList.contains('current') ? `[${el.textContent?.trim()}]` : el.textContent?.trim(),
    );
    const num = nav.querySelector('.page-numbers') as HTMLElement | null;
    const s = num ? getComputedStyle(num) : null;
    return {
      items,
      next: !!nav.querySelector('.wp-block-query-pagination-next'),
      previous: !!nav.querySelector('.wp-block-query-pagination-previous'),
      font: s ? `${s.fontSize}/${s.fontWeight}` : '',
      border: s ? s.borderTopWidth + ' ' + s.borderTopStyle : '',
    };
  });
}

test.describe('listings QA', () => {
  test.beforeEach(async ({ page }) => {
    await preparePage(page);
  });

  test('QA-004 browse-issues groups covers by year like WP @ 1280px', async ({ page }) => {
    await open(page, '/browse-issues');
    const covers = page.locator('figure.issue-cover');
    await expect(covers).toHaveCount(15);
    const layout = await page.evaluate(() => {
      const rows = [...document.querySelectorAll('.wp-container-core-group-is-layout-7caa2bd2')];
      return {
        rows: rows.length,
        firstRowColumns: rows[0]?.querySelectorAll(':scope > .wp-block-group').length ?? 0,
        headingLinks: document.querySelectorAll('.wp-container-core-group-is-layout-7caa2bd2 h4 a').length,
      };
    });
    expect(layout.rows).toBeGreaterThanOrEqual(4);
    expect(layout.firstRowColumns).toBe(2);
    expect(layout.headingLinks).toBe(0);
  });

  for (const path of ['/category/cancer-research', '/category/pediatrics', '/category/immunotherapy']) {
    test(`QA-005/006 page 1 of ${path} has a WP paginate_links pager`, async ({ page }) => {
      await open(page, path);
      const pager = await pagerSummary(page);
      expect(pager).not.toBeNull();
      expect(pager!.items[0]).toBe('[1]');
      expect(pager!.items).toContain('2');
      expect(pager!.next).toBe(true);
      expect(pager!.previous).toBe(false);
      expect(pager!.font).toBe('18px/600');
      expect(pager!.border).toBe('2px solid');
      const last = pager!.items[pager!.items.length - 1];
      expect(Number(last)).toBeGreaterThan(2);
    });
  }

  test('QA-007/030 search uses WP list rows and pager with no axe list violations', async ({ page }) => {
    await open(page, '/search?s=cancer');
    const rows = page.locator('ul.wp-block-post-template > li.wp-block-post');
    await expect(rows).toHaveCount(10);
    await expect(page.locator('ul.wp-block-post-template > :not(li)')).toHaveCount(0);
    await expect(rows.first().locator('h2.wp-block-post-title mark.bsearch_highlight').first()).toBeVisible();
    const box = await rows.first().boundingBox();
    expect(Math.round(box!.x)).toBe(64);
    expect(Math.round(box!.width)).toBe(1152);
    const pager = await pagerSummary(page);
    expect(pager?.items.slice(0, 2)).toEqual(['[1]', '2']);
    expect(pager?.next).toBe(true);
    const nextHref = await page.locator('.wp-block-query-pagination-next').getAttribute('href');
    expect(nextHref).toMatch(/^\/search\?s=cancer&page=1$/);
    const axe = await new AxeBuilder({ page }).withRules(['list', 'listitem']).analyze();
    expect(axe.violations.map((v) => v.id)).toEqual([]);
  });

  test('QA-007 WP-style /page/N/?s= search URLs redirect to the Drupal pager', async ({ request }) => {
    const response = await request.get(`${drupalBase}/page/3/?s=cancer`, { maxRedirects: 0 });
    expect(response.status()).toBe(302);
    expect(response.headers()['location']).toMatch(/\/search\?s=cancer&page=2$/);
  });
});
