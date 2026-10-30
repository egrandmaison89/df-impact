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

  for (const path of ['/digital-exclusives', '/topics', '/late-fall-2022-issue', '/no-such-page-qa022']) {
    test(`QA-008/022 ${path} serves the WP 404 page`, async ({ page }) => {
      const response = await open(page, path);
      expect(response?.status()).toBe(404);
      const h1 = page.locator('h1.blue-border-header');
      await expect(h1).toHaveText('OOPS!');
      const h1Style = await h1.evaluate((el) => {
        const s = getComputedStyle(el);
        return [s.fontSize, s.fontWeight, s.color, s.borderLeftWidth, s.borderLeftColor].join(' ');
      });
      expect(h1Style).toBe('38px 600 rgb(0, 51, 84) 5px rgb(0, 98, 155)');
      await expect(page.locator('main p.has-text-align-center')).toHaveText('This page could not be found. Maybe try a search?');
      const button = page.locator('main form.wp-block-search .wp-block-search__button');
      const box = await button.boundingBox();
      expect([Math.round(box!.width), Math.round(box!.height)]).toEqual([48, 48]);
      await expect(page.locator('main form.wp-block-search input[name="s"]')).toBeVisible();
    });
  }

  test('QA-009 /past-issues has no pager', async ({ page }) => {
    const response = await open(page, '/past-issues');
    expect(response?.status()).toBe(200);
    await expect(page.locator('nav.wp-block-query-pagination, .page-numbers, nav.pager')).toHaveCount(0);
  });

  test('QA-010 /in-brief cards have WP thumbnails @ 1280px', async ({ page }) => {
    await open(page, '/in-brief');
    const first = page.locator('.ultp-block-item:not(.is-collapsed) img.ultp-block-image-content').first();
    await expect(first).toBeVisible();
    await expect(first).toHaveAttribute('src', /AACR_1450\.jpg/);
    const box = await first.boundingBox();
    expect([Math.round(box!.x), Math.round(box!.width), Math.round(box!.height)]).toEqual([64, 346, 248]);
    const visibleWithoutImage = await page.locator('.ultp-block-item:not(.is-collapsed):not(:has(img))').count();
    expect(visibleWithoutImage).toBe(0);
  });

  for (const width of [1280, 390]) {
    test(`QA-012 article category pills are WP title-case, alphabetical @ ${width}px`, async ({ page }) => {
      await open(page, '/2024/09/palumbo-family-moves-needle-for-patients-with-rare-pediatric-cancers', width);
      const links = page.locator('.taxonomy-category.wp-block-post-terms > a');
      await expect(links).toHaveText(['Cancer Research', 'Pediatrics', 'Rare and Resistant Cancers', 'Total Patient Care']);
      const styles = await links.evaluateAll((els) =>
        els.map((el) => {
          const s = getComputedStyle(el);
          const r = el.getBoundingClientRect();
          return { font: `${s.fontFamily.split(',')[0]} ${s.fontWeight} ${s.fontSize}`, transform: s.textTransform, color: s.color, deco: s.textDecorationLine, x: Math.round(r.x), y: Math.round(r.y), h: Math.round(r.height) };
        }),
      );
      for (const s of styles) {
        expect(s.font).toBe('Montserrat 600 18px');
        expect(s.transform).toBe('none');
        expect(s.color).toBe('rgb(0, 98, 155)');
        expect(s.deco).toBe('underline');
        expect(s.h).toBe(35);
      }
      await expect(page.locator('.taxonomy-category .wp-block-post-terms__separator').first()).toBeHidden();
      if (width === 390) {
        expect(styles[2].x).toBe(styles[0].x);
        expect(styles[3].y).toBeGreaterThan(styles[2].y);
      }
    });
  }

  for (const path of ['/2023/01/rossy-foundation-ribbon-cutting', '/2023/01/michele-and-howard-kessler', '/2026/09/american-association-for-cancer-research-2']) {
    test(`QA-015 In Brief ${path} uses the WP single-post template`, async ({ page }) => {
      await open(page, path);
      const h1 = page.locator('h1.wp-block-post-title');
      await expect(h1).toHaveCSS('color', 'rgb(0, 51, 84)');
      await expect(page.locator('main .ss-inline-share-wrapper')).toBeVisible();
      expect(await page.locator('.taxonomy-category.wp-block-post-terms > a').count()).toBeGreaterThan(0);
      await expect(page.locator('main h3', { hasText: 'More stories:' })).toBeVisible();
      await expect(page.locator('.jp-related-posts-i2__post')).toHaveCount(3);
    });
  }

  test('QA-015 articles show WP Jetpack "More stories"', async ({ page }) => {
    await open(page, '/2024/09/palumbo-family-moves-needle-for-patients-with-rare-pediatric-cancers');
    const cards = page.locator('.jp-related-posts-i2__post');
    await expect(cards).toHaveCount(3);
    const box = await cards.first().boundingBox();
    expect([Math.round(box!.width), Math.round(box!.height)]).toEqual([373, 309]);
  });

  test('QA-022 404 search form lands on search results', async ({ page }) => {
    await open(page, '/digital-exclusives');
    await page.fill('main .wp-block-search__input', 'cancer');
    await Promise.all([page.waitForURL(/\/search\?s=cancer$/), page.click('main .wp-block-search__button')]);
    await expect(page.locator('ul.wp-block-post-template > li.wp-block-post').first()).toBeVisible();
  });

  test('QA-016 drop cap only on WP dropCap paragraphs', async ({ page }) => {
    const firstLetters = () =>
      page.evaluate(() =>
        [...document.querySelectorAll('main .entry-content > p')].slice(0, 3).map((p) => {
          const f = getComputedStyle(p, '::first-letter');
          return `${p.classList.contains('has-drop-cap') ? 'dc' : 'p'}:${f.float}`;
        }),
      );
    await open(page, '/2024/03/a-message-from-melany-duval-4');
    expect(await firstLetters()).toEqual(['p:none', 'p:none', 'p:none']);
    await open(page, '/2022/11/rasmussen-family-funds-research-into-underlying-causes-of-colorectal-cancer');
    expect(await firstLetters()).toEqual(['p:none', 'dc:left', 'p:none']);
  });

  test('QA-017 issue label prints once when the body already carries it', async ({ page }) => {
    await open(page, '/2023/11/dismantling-gender-disparity-through-mentorship');
    await expect(page.locator('main .entry-content a', { hasText: /^Winter 2023$/ })).toHaveCount(1);
    await expect(page.locator('main .entry-content > p').first()).toHaveText(/^Winter 2023\s*By Amber Sinicrope/);
  });

  for (const [width, figure, side] of [
    [1280, [569, 407], true],
    [768, [338, 242], true],
    [390, [350, 251], false],
  ] as const) {
    test(`QA-018 gallery images sit side by side like WP @${width}`, async ({ page }) => {
      await open(page, '/2024/06/the-gray-foundation-empowers-team-science-against-brca-mutated-cancers', width);
      const figures = page.locator('main .wp-block-gallery > figure.wp-block-image');
      await expect(figures).toHaveCount(2);
      await figures.first().scrollIntoViewIfNeeded();
      await expect(figures.first().locator('img')).toHaveJSProperty('complete', true);
      const boxes = await figures.evaluateAll((els) => els.map((el) => el.getBoundingClientRect().toJSON()));
      expect(boxes.map((b) => [Math.round(b.width), Math.round(b.height)])).toEqual([figure, figure]);
      expect(Math.round(boxes[1].y) === Math.round(boxes[0].y)).toBe(side);
      expect(await figures.first().locator('img').evaluate((img: HTMLImageElement) => img.naturalWidth)).toBe(1024);
    });
  }

  test('QA-018 articles without a leading body image keep every gallery photo and get no injected lead figure', async ({ page }) => {
    await open(page, '/2024/03/jimmy-fund-golf-community-helps-to-usher-in-the-jimmy-funds-75th');
    await expect(page.locator('main .entry-content > :first-child')).toHaveText(/^\s*Spring 2024/);
    await expect(page.locator('main .wp-block-gallery > figure.wp-block-image')).toHaveCount(8);
    expect(await page.locator('main .entry-content img[src*="//default/"]').count()).toBe(0);
  });

  test('QA-021 quote citation is small, plain and dark gray like WP', async ({ page }) => {
    await open(page, '/2023/11/dismantling-gender-disparity-through-mentorship');
    const quote = page.locator('main blockquote.wp-block-quote').first();
    await expect(quote).toHaveCSS('margin-left', '0px');
    await expect(quote).toHaveCSS('padding-left', '20px');
    await expect(quote.locator('p').first()).toHaveCSS('margin-bottom', '10px');
    const cite = quote.locator('cite');
    await expect(cite).toHaveCSS('display', 'block');
    await expect(cite).toHaveCSS('font-size', '16.25px');
    await expect(cite).toHaveCSS('font-style', 'normal');
    await expect(cite).toHaveCSS('color', 'rgb(17, 17, 17)');
    await expect(cite).toHaveCSS('margin-top', '24px');
  });

  test('QA-007 WP-style /page/N/?s= search URLs redirect to the Drupal pager', async ({ request }) => {
    const response = await request.get(`${drupalBase}/page/3/?s=cancer`, { maxRedirects: 0 });
    expect(response.status()).toBe(302);
    expect(response.headers()['location']).toMatch(/\/search\?s=cancer&page=2$/);
  });
});
