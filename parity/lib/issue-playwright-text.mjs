/** Playwright main-text extract for issue landings (matches issue-live-text-sweep). */

import {
  extractMainText,
  gotoWithBoundedSettle,
  preparePage,
  textSimilarity,
} from './page-utils.mjs';
import { stabilizeParityViewport } from './capture-stabilize.mjs';

export function prepareWpIssueFixtureHtml(html) {
  return html
    .replace(/\sloading="lazy"/gi, ' loading="eager"')
    .replace(/<img([^>]*?)\sdata-src="([^"]+)"([^>]*)>/gi, (full, before, src, after) => {
      if (/\ssrc="/i.test(before + after)) {
        return full;
      }
      return `<img${before} src="${src}" data-src="${src}"${after}>`;
    });
}

/**
 * @param {import('@playwright/test').Page} page
 * @param {{ wpHtml: string, drupalUrl: string, drupalHtml?: string }} opts
 */
export async function issueLandingTextSimilarity(page, { wpHtml, drupalUrl, drupalHtml }) {
  await page.setContent(prepareWpIssueFixtureHtml(wpHtml), {
    waitUntil: 'domcontentloaded',
    timeout: 120000,
    url: 'https://danafarberimpact.org/',
  });
  await stabilizeParityViewport(page);
  const wpText = (await extractMainText(page)).text;
  if (drupalHtml) {
    await page.setContent(drupalHtml, {
      waitUntil: 'domcontentloaded',
      timeout: 120000,
      url: drupalUrl.endsWith('/') ? drupalUrl : `${drupalUrl}/`,
    });
  } else {
    await gotoWithBoundedSettle(page, drupalUrl, { timeout: 120000 });
  }
  await stabilizeParityViewport(page);
  const drText = (await extractMainText(page)).text;
  return textSimilarity(wpText, drText);
}

export async function createIssueParityPage(browser) {
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await preparePage(page);
  return page;
}
