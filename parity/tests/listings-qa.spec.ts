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
    expect(nextHref).toMatch(/^\/page\/2\/\?s=cancer$/);
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
    await Promise.all([page.waitForURL(/\/\?s=cancer$/), page.click('main .wp-block-search__button')]);
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
    [390, [334, 239], false],
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

  const qa019Captions: Record<string, (string | null)[]> = {
    '/2023/01/edward-p-evans-foundation-grants-propel-mds-research': [null, 'R. Coleman Lindsley, MD, PhD.'],
    '/2024/06/the-gray-foundation-empowers-team-science-against-brca-mutated-cancers': [null, 'Judy Garber, MD, MPH', 'Dipanjan Chowdhury, PhD'],
    '/2026/09/a-message-from-melany-duval-13': ['Photography by Cheryl Richards'],
    '/2023/11/dismantling-gender-disparity-through-mentorship': ['Helen Gurley Brown.'],
    '/2026/09/more-than-a-building-name': ['Anita and Josh Bekenstein and Jeannie and Jonathan Lavine'],
    '/2024/03/lindsley-named-edward-p-evans-investigator': ['Pictured at the celebration: Lindsley (left) and foundation President Michael D. Lewis, PhD.'],
    '/2024/06/st-baldricks-foundation-2': [null],
  };
  for (const width of [1280, 768, 390]) {
    test(`QA-019 lead image keeps only its own caption like WP @${width}`, async ({ page }) => {
      for (const [path, expected] of Object.entries(qa019Captions)) {
        await open(page, path, width);
        const captions = await page.locator('main .entry-content figure.wp-block-image').evaluateAll((figs) =>
          figs.map((f) => f.querySelector(':scope > figcaption')?.textContent?.trim() || null),
        );
        expect(captions.slice(0, expected.length), path).toEqual(expected);
        expect(captions.filter(Boolean).length, path).toBe(expected.filter(Boolean).length);
      }
    });
  }

  const qa020Live: Record<string, [string, string][]> = {
    '/2023/01/edward-p-evans-foundation-grants-propel-mds-research': [
      ['/2025/06/edward-p-evans-foundation-takes-aim-at-blood-disorders', 'EvansMDS_Logo_725.jpg'],
      ['/2024/03/edward-p-evans-foundation-grants-spur-cutting-edge-mds-research', 'EvansMDS_Logo_725.jpg'],
      ['/2026/06/advancing-blood-cancer-research-with-the-edward-p-evans-foundation', 'EvansMDS_Logo_725.jpg'],
    ],
    '/2024/06/the-gray-foundation-empowers-team-science-against-brca-mutated-cancers': [
      ['/2024/03/the-paula-and-rodger-riney-foundation-sustains-momentum-in-multiple-myeloma-research', 'DFI_Rineys-illum1.jpg'],
      ['/2024/06/st-baldricks-foundation-2', 'StBaldricks_1450.jpg'],
      ['/2025/06/breast-cancer-research-foundation-grants-more-than-4-million-to-speed-dana-farber-research', 'BCRF_1450.jpg'],
    ],
    '/2026/09/a-message-from-melany-duval-13': [
      ['/2026/06/a-message-from-melany-duval-12', 'Melany_Duval.jpg'],
      ['/2025/06/a-message-from-melany-duval-9', 'Melany_Duval.jpg'],
      ['/2024/09/a-message-from-melany-duval-6', 'Melany_Duval.jpg'],
    ],
    '/2023/11/dismantling-gender-disparity-through-mentorship': [
      ['/2026/06/the-michael-helen-r-cyker-charitable-foundation', 'Impact-InBrief11-1450.jpg'],
      ['/2025/06/helen-hay-whitney-foundation', 'Impact-InBrief8-1450.jpg'],
      ['/2025/11/craig-and-bettina-burr', 'Impact-InBrief8-1450.jpg'],
    ],
    '/2026/09/more-than-a-building-name': [
      ['/2026/09/a-new-era-in-cancer-care-lavine-bekenstein-cancer-hospital-at-dana-farber', '25_0610_View-from-Brookline-Ave_night-scaled.jpg'],
      ['/2026/09/honoring-a-foundation-of-leadership', 'PresidentsFloor_1450.jpg'],
      ['/2025/03/the-dana-farber-campaign-cabinet', 'DFI_JCP_8.14.24_0020_edit.jpg'],
    ],
    '/2024/03/lindsley-named-edward-p-evans-investigator': [
      ['/2023/01/edward-p-evans-foundation-grants-propel-mds-research', 'EvansMDS_Logo_725.jpg'],
      ['/2025/03/the-dana-farber-campaign-stopping-cancer-before-it-starts', 'DFI_SO_07167_24.jpg'],
      ['/2026/06/advancing-blood-cancer-research-with-the-edward-p-evans-foundation', 'EvansMDS_Logo_725.jpg'],
    ],
    '/2024/06/st-baldricks-foundation-2': [
      ['/2023/07/st-baldricks-foundation', 'StBaldricks_1450.jpg'],
      ['/2025/03/st-baldricks-foundation-3', 'StBaldricks_1450.jpg'],
      ['/2023/01/grants-support-innovations-in-pediatric-cancer-research', 'HyundaiHopeOnWheelsLogo_1450.jpg'],
    ],
  };
  for (const [width, card] of [
    [1280, [373, 213]],
    [768, [220, 126]],
    [390, [334, 191]],
  ] as const) {
    test(`QA-020 "More stories:" lists live's Jetpack items in order with thumbnails @${width}`, async ({ page }) => {
      for (const [path, expected] of Object.entries(qa020Live)) {
        await open(page, path, width);
        const group = page.locator('main .wp-block-group:has(> nav.jp-relatedposts-i2)');
        await expect(group.locator('h3.wp-block-heading.has-montserrat-font-family'), path).toHaveText('More stories:');
        expect(await group.evaluate((g) => g.previousElementSibling?.classList.contains('taxonomy-category')), path).toBe(true);
        await expect(group.locator('ul.jp-related-posts-i2__list'), path).toHaveAttribute('data-post-count', '3');
        const items = await group.locator('li.jp-related-posts-i2__post > a.jp-related-posts-i2__post-link').evaluateAll((links) =>
          links.map((a) => [
            a.getAttribute('href') ?? '',
            (a.querySelector('img.jp-related-posts-i2__post-img')?.getAttribute('src') ?? '').replace(/[?#].*$/, '').split('/').pop() ?? '',
          ]),
        );
        expect(items, path).toEqual(expected);
        if (path.includes('the-gray-foundation')) {
          const img = group.locator('img.jp-related-posts-i2__post-img').first();
          await img.scrollIntoViewIfNeeded();
          const box = await img.boundingBox();
          expect([Math.round(box!.width), Math.round(box!.height)]).toEqual(card);
        }
      }
    });
  }

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

  for (const [width, h1Box, coverBox] of [
    [1280, [721, 331, 515], [0, 148, 1280, 481]],
    [768, [38, 378, 711], [0, 191, 768, 481]],
  ] as const) {
    test(`QA-023 issue hero title sits where WP centers it @${width}`, async ({ page }) => {
      await open(page, '/fall-2026-issue', width);
      const box = (sel: string) =>
        page.locator(sel).first().evaluate((el) => {
          const r = el.getBoundingClientRect();
          return [Math.round(r.x), Math.round(r.y + window.scrollY), Math.round(r.width), Math.round(r.height)];
        });
      expect((await box('main h1.ultp-heading-inner')).slice(0, 3)).toEqual(h1Box);
      expect(await box('main .wp-block-cover.alignfull')).toEqual(coverBox);
    });
  }

  test('QA-024 "More than a building name" card shows the four-people photo', async ({ page }) => {
    await open(page, '/fall-2026-issue');
    const img = page.locator('main .ultp-block-item', { hasText: 'More than a building name' }).locator('img').first();
    await img.scrollIntoViewIfNeeded();
    await expect.poll(() => img.evaluate((el: HTMLImageElement) => el.currentSrc)).toContain('LavineBekensteinFCH_1450');
    expect((await img.getAttribute('srcset')) ?? '').not.toMatch(/\/FCH_1450/);
  });

  test('QA-029 tag archive title matches WP and the pager has 6 pages', async ({ page }) => {
    await open(page, '/tag/digital-exclusives');
    await expect(page).toHaveTitle('Digital Exclusives Archives - Impact Magazine');
    await expect(page.locator('meta[property="og:title"]')).toHaveAttribute('content', 'Digital Exclusives Archives - Impact Magazine');
    const pager = await pagerSummary(page);
    expect(pager).not.toBeNull();
    expect(pager!.items[0]).toBe('[1]');
    expect(pager!.items).toContain('6');
    expect(pager!.next).toBe(true);
    await expect(page.locator('.wp-block-query-pagination-next')).toHaveAttribute('href', /\/tag\/digital-exclusives\/page\/2\/?$/);
  });

  test('QA-065 YouTube embed stays inside a 390px viewport', async ({ page }) => {
    await open(page, '/2026/09/a-legacy-of-giving-that-makes-compassionate-care-possible/', 390);
    const frame = page.locator('figure.wp-block-embed-youtube iframe').first();
    await expect(frame).toBeVisible();
    const box = await frame.boundingBox();
    expect(box).not.toBeNull();
    expect(box!.width).toBeLessThanOrEqual(390);
    expect(box!.width).toBeGreaterThan(200);
    const ratio = box!.width / box!.height;
    expect(ratio).toBeGreaterThan(1.6);
    expect(ratio).toBeLessThan(1.9);
    const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
    expect(scrollWidth).toBeLessThanOrEqual(390);
  });

  test('QA-064 pull quote uses live Libre Baskerville styles', async ({ page }) => {
    await open(page, '/2025/03/sajni-fund-raises-vital-funds-for-rare-pediatric-cancer-research/');
    const quote = page.locator('figure.wp-block-pullquote').first();
    await expect(quote).toBeVisible();
    await expect(quote).toHaveCSS('font-size', '30px');
    await expect(quote).toHaveCSS('font-weight', '600');
    await expect(quote).toHaveCSS('border-top-color', 'rgb(255, 163, 0)');
    const family = await quote.evaluate((el) => getComputedStyle(el).fontFamily);
    expect(family.toLowerCase()).toContain('libre baskerville');
    const cite = quote.locator('cite').first();
    await expect(cite).toHaveCSS('font-size', '24.375px');
    await expect(cite).toHaveCSS('text-transform', 'capitalize');
  });

  test('QA-010/015 In Brief keeps the summary lead figure and caption like WP', async ({ page }) => {
    await open(page, '/2026/03/arbella-insurance-foundation-3');
    const figure = page.locator('main .entry-content > figure.wp-block-image').first();
    await expect(page.locator('main .entry-content > :first-child')).toHaveClass(/wp-block-image/);
    await expect(figure.locator('img')).toHaveAttribute('src', /ArbellaCheck_1450/);
    await expect(figure.locator('figcaption')).toContainText('Representatives from Arbella Insurance Group');
  });

  test('QA-007 WP-style /page/N/?s= search URLs render that results page in place', async ({ page, request }) => {
    const response = await request.get(`${drupalBase}/page/3/?s=cancer`, { maxRedirects: 0 });
    expect(response.status()).toBe(200);
    await open(page, '/page/3/?s=cancer');
    const pager = await pagerSummary(page);
    expect(pager?.items).toContain('[3]');
    await expect(page.locator('.wp-block-query-pagination-previous')).toHaveAttribute('href', /^\/page\/2\/\?s=cancer$/);
    await expect(page.locator('.wp-block-query-pagination-numbers a.page-numbers', { hasText: /^1$/ })).toHaveAttribute('href', /^\/\?s=cancer$/);
  });
});
