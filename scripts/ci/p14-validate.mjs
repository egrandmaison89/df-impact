/**
 * QA pass 14 editor validation.
 *
 * Empty Byline and an image caption without an image show a form error.
 * Required paragraph subfields, including the CTA Button URL, show a Drupal
 * message instead of failing silently. Save while the media library is open
 * shows a message and does not post. A valid save keeps Pull Quote and Image
 * with Caption. A published node with a newer draft shows a View banner.
 * Revisions help does not offer revert to web_editor or site_admin. A Basic
 * page save shows the standard status message. Preview uses the node title
 * and does not throw the dialog TypeError. A long form scrolls to the first
 * error. Featured-image upload dialogs block Save. In Brief preview, save,
 * and issue placement, Publish issue, and the Footer menu are covered too.
 */
import { createRequire } from 'node:module';

const require = createRequire((process.env.PLAYWRIGHT_CORE_ROOT || '/tmp/df-playwright') + '/package.json');
const { chromium } = require('playwright-core');

const base = (process.env.BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const chrome = process.env.CHROME_PATH || '/usr/local/bin/google-chrome';
const quote = 'Playwright pull quote for QA-101';

const fail = (message) => {
  console.error(message);
  process.exitCode = 1;
};

const browser = await chromium.launch({
  executablePath: chrome,
  headless: true,
  args: ['--no-sandbox', '--disable-dev-shm-usage'],
});

try {
  await run();
}
finally {
  await browser.close();
}

if (process.exitCode) {
  process.exit(process.exitCode);
}

async function run() {
  const admin = await openSession(process.env.ADMIN_LOGIN);
  let articlePath = '';
  try {
    await assertBylineError(admin.page, 'admin');
    await assertParagraphRequired(admin.page);
    await assertImageAndDialog(admin.page);
    await assertValidSave(admin.page);
    articlePath = await assertPublishedDraftWarning(admin.page);
    await assertNewerDraftBanner(admin.page, articlePath);
    await assertPreview(admin.page);
    await assertInBriefPreview(admin.page);
    await assertGalleryErrorScrolls(admin.page);
    await assertFeaturedImageDialog(admin.page);
    await assertIssueBrief(admin.page);
    await assertFooterMenu(admin.page);
    await assertInBriefSaveMessage(admin.page);
    await assertPublishIssueMessage(admin.page);
    await assertRevisionHelp(admin.page, articlePath, true);
  }
  finally {
    await admin.context.close();
  }

  for (const [role, login] of [['web_editor', process.env.WEB_EDITOR_LOGIN], ['site_admin', process.env.SITE_ADMIN_LOGIN]]) {
    if (!login) {
      fail(`missing login for ${role}`);
      return;
    }
    const session = await openSession(login);
    try {
      await assertBylineError(session.page, role);
      if (articlePath) {
        await assertRevisionHelp(session.page, articlePath, false);
      }
      if (role === 'site_admin') {
        await assertPageSaveMessage(session.page);
      }
    }
    finally {
      await session.context.close();
    }
  }
  console.log('p14 validation passed');
}

async function openSession(login) {
  if (!login) {
    throw new Error('missing login URL');
  }
  const context = await browser.newContext();
  const page = await context.newPage();
  page.setDefaultTimeout(25000);
  await page.goto(login, { waitUntil: 'domcontentloaded' });
  return { context, page };
}

async function assertBylineError(page, role) {
  await page.goto(`${base}/node/add/article`, { waitUntil: 'domcontentloaded' });
  const form = page.locator('#node-article-form');
  if (await form.getAttribute('novalidate') === null) {
    throw new Error(`${role}: article form is missing novalidate`);
  }
  await page.locator('#edit-title-0-value').fill(`Playwright byline ${role}`);
  await page.locator('#edit-field-byline-0-value').fill('');
  await page.locator('#gin-sticky-edit-submit').click();
  const summary = page.locator('.df-editor-validation-errors');
  await summary.waitFor();
  const summaryText = await summary.innerText();
  if (!/byline/i.test(summaryText)) {
    throw new Error(`${role}: form summary missing Byline: ${summaryText}`);
  }
  const fieldText = await page.locator('#edit-field-byline-wrapper').innerText();
  if (!/required/i.test(fieldText)) {
    throw new Error(`${role}: Byline field has no visible error: ${fieldText}`);
  }
  const banner = page.locator('[data-drupal-messages]');
  if (await banner.count()) {
    const bannerText = await banner.innerText();
    if (/byline field is required/i.test(bannerText)) {
      throw new Error(`${role}: Byline error is inside the status banner`);
    }
  }
  console.log(`${role}: empty Byline shows a form error`);
}

async function clickCoveredSave(page) {
  const box = await page.locator('#gin-sticky-edit-submit').boundingBox();
  if (!box) {
    throw new Error('sticky Save is missing');
  }
  await page.mouse.click(box.x + box.width / 2, box.y + box.height / 2);
}

async function clickDrupalAjax(locator) {
  await locator.waitFor({ state: 'attached' });
  // Paragraph add buttons are secondary dropbutton actions (visibility:hidden).
  // Drupal binds their Ajax to mousedown, which a coordinate click never hits.
  await locator.evaluate((el) => {
    el.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true, button: 0 }));
  });
}

async function clickParagraphAdd(page, buttonId) {
  const ajax = page.waitForResponse((response) => response.request().method() === 'POST' && response.url().includes('ajax_form=1'));
  await clickDrupalAjax(page.locator(buttonId));
  await ajax;
}

async function assertParagraphRequired(page) {
  await page.goto(`${base}/node/add/article`, { waitUntil: 'domcontentloaded' });
  await page.locator('#edit-title-0-value').fill('Playwright paragraph fields');
  await page.locator('#edit-field-byline-0-value').fill('QA Editor');
  const types = [
    ['image_caption', 'input[name*="field_para_image-media-library-open-button"]'],
    ['pull_quote', 'textarea[name*="field_quote_text"]'],
    ['cta', 'input[name*="field_cta_button_url"]'],
    ['text_block', 'textarea[name*="field_text_body"]'],
    ['stat_highlight', 'input[name*="field_stat_number"]'],
    ['video_embed', 'input[name*="field_video_url"]'],
    ['image_gallery', '[name*="field_gallery_items"]'],
  ];
  for (const [type, field] of types) {
    await clickParagraphAdd(page, `[name="field_content_sections_${type}_add_more"]`);
    await page.locator(field).first().waitFor({ state: 'attached' });
  }
  const galleryAdd = page.locator('input[name*="field_gallery_items"][name*="add_more"]').first();
  if (await galleryAdd.count()) {
    const ajax = page.waitForResponse((response) => response.request().method() === 'POST' && response.url().includes('ajax_form=1'));
    await clickDrupalAjax(galleryAdd);
    await ajax;
  }
  await page.locator('#gin-sticky-edit-submit').click();
  const summary = page.locator('.df-editor-validation-errors');
  try {
    await summary.waitFor({ timeout: 25000 });
  }
  catch (error) {
    const text = await page.locator('body').innerText();
    throw new Error(`paragraph form error missing at ${page.url()}: ${text.replace(/\s+/g, ' ').slice(0, 400)}`);
  }
  const summaryText = await summary.innerText();
  const expected = [
    /image/i,
    /quote text/i,
    /url field is required/i,
    /button text/i,
    /heading/i,
    /body/i,
    /label/i,
    /number/i,
    /video url/i,
  ];
  const missing = expected.filter((pattern) => !pattern.test(summaryText)).map((pattern) => pattern.source);
  if (missing.length) {
    throw new Error(`paragraph required errors missing (${missing.join(', ')}): ${summaryText}`);
  }
  const banner = page.locator('[data-drupal-messages]');
  if (await banner.count()) {
    const bannerText = await banner.innerText();
    if (/field is required/i.test(bannerText)) {
      throw new Error(`paragraph errors are inside the status banner: ${bannerText}`);
    }
  }
  console.log('required paragraph subfields show a form error');
}

async function assertImageAndDialog(page) {
  await page.goto(`${base}/node/add/article`, { waitUntil: 'domcontentloaded' });
  await page.locator('#edit-title-0-value').fill('Playwright validation article');
  await page.locator('#edit-field-byline-0-value').fill('QA Editor');
  await clickParagraphAdd(page, '[name="field_content_sections_image_caption_add_more"]');
  const openLibrary = page.locator('input[name*="field_para_image-media-library-open-button"]');
  await openLibrary.waitFor();
  await page.locator('#gin-sticky-edit-submit').click();
  const summary = page.locator('.df-editor-validation-errors');
  await summary.waitFor();
  const summaryText = await summary.innerText();
  if (!/image/i.test(summaryText)) {
    throw new Error(`image error missing from form summary: ${summaryText}`);
  }
  const paragraph = page.locator('.df-editor-paragraph-error');
  await paragraph.first().waitFor();
  const paragraphText = await paragraph.first().innerText();
  if (!/image/i.test(paragraphText)) {
    throw new Error(`image error missing from the paragraph: ${paragraphText}`);
  }
  const banner = page.locator('[data-drupal-messages]');
  if (await banner.count()) {
    const bannerText = await banner.innerText();
    if (/image field is required/i.test(bannerText)) {
      throw new Error('Image error is inside the status banner');
    }
  }
  console.log('image_caption without an image shows a form error');

  await openLibrary.click();
  const dialog = page.locator('.ui-dialog.media-library-widget-modal');
  await dialog.waitFor();
  const checkbox = dialog.locator('.js-click-to-select-checkbox input').first();
  await checkbox.waitFor();
  await checkbox.check();
  await clickCoveredSave(page);
  const blocked = page.locator('.df-editor-media-dialog-block');
  await blocked.waitFor();
  const blockedText = await blocked.innerText();
  if (!/media library is still open/i.test(blockedText)) {
    throw new Error(blockedText);
  }
  if (!page.url().includes('/node/add/article')) {
    throw new Error(`Save posted while the media dialog was open: ${page.url()}`);
  }
  console.log('Save with the media dialog open shows a message');
  await page.getByRole('button', { name: 'Insert selected' }).click();
  await dialog.waitFor({ state: 'hidden' });
}

async function assertValidSave(page) {
  await page.goto(`${base}/node/add/article`, { waitUntil: 'domcontentloaded' });
  await page.locator('#edit-title-0-value').fill('Playwright image article');
  await page.locator('#edit-field-byline-0-value').fill('QA Editor');
  await clickParagraphAdd(page, '[name="field_content_sections_image_caption_add_more"]');
  await page.locator('input[name*="field_para_image-media-library-open-button"]').click();
  const dialog = page.locator('.ui-dialog.media-library-widget-modal');
  await dialog.waitFor();
  await dialog.locator('.js-click-to-select-checkbox input').first().check();
  await page.getByRole('button', { name: 'Insert selected' }).click();
  await dialog.waitFor({ state: 'hidden' });
  await page.waitForFunction(() => {
    const input = document.querySelector('[name*="field_para_image"][name*="target_id"]');
    return input && input.value !== '';
  });
  await page.locator('#gin-sticky-edit-submit').click();
  await page.waitForURL(/\/node\/\d+\/edit/);
  const selectedImage = page.locator('.media-library-item, [name*="field_para_image"][name*="target_id"]');
  if (await selectedImage.count() === 0) {
    throw new Error('Image with Caption was not saved');
  }

  await page.goto(`${base}/node/add/article`, { waitUntil: 'domcontentloaded' });
  await page.locator('#edit-title-0-value').fill('Playwright validation article');
  await page.locator('#edit-field-byline-0-value').fill('QA Editor');
  await clickParagraphAdd(page, '[name="field_content_sections_pull_quote_add_more"]');
  const quoteField = page.locator('textarea[name*="field_quote_text"]');
  await quoteField.waitFor({ state: 'attached' });
  await quoteField.evaluate((el, value) => {
    const editor = window.Drupal.CKEditor5Instances.get(el.getAttribute('data-ckeditor5-id'));
    if (editor) {
      editor.setData(`<p>${value}</p>`);
      if (typeof editor.updateSourceElement === 'function') {
        editor.updateSourceElement();
      }
      return;
    }
    el.value = value;
  }, quote);
  await page.locator('#gin-sticky-edit-submit').click();
  await page.waitForURL(/\/node\/\d+\/edit/);
  const saved = await page.locator('[data-drupal-messages], .messages--status').first().innerText();
  if (!/has been (created|updated)/i.test(saved)) {
    throw new Error(`article save is missing the status message: ${saved}`);
  }
  const body = await page.locator('#node-article-edit-form, #node-article-form').innerText();
  if (!body.includes(quote)) {
    throw new Error('Pull Quote text was not saved');
  }
  console.log('valid save persisted Pull Quote and Image with Caption');
}

async function assertPublishedDraftWarning(page) {
  await page.locator('#edit-moderation-state-0-state').selectOption('published');
  await page.locator('#gin-sticky-edit-submit').click();
  await page.waitForURL(/\/node\/\d+\/edit/);
  const notice = page.locator('.df-editor-published-draft-notice');
  await notice.waitFor();
  const noticeText = await notice.innerText();
  if (!/live published version/i.test(noticeText) || !/latest version/i.test(noticeText)) {
    throw new Error(noticeText);
  }
  await page.locator('#edit-moderation-state-0-state').selectOption('draft');
  page.once('dialog', async (dialog) => {
    if (!/live published version/i.test(dialog.message()) || !/latest version/i.test(dialog.message())) {
      throw new Error(dialog.message());
    }
    await dialog.accept();
  });
  await page.locator('#gin-sticky-edit-submit').click();
  await page.waitForURL(/\/node\/\d+\/edit/);
  const messages = await page.locator('body').innerText();
  if (!/saved as a draft/i.test(messages)) {
    throw new Error('draft save confirmation missing');
  }
  console.log('published article warns that a draft stays off the live View');
  const match = page.url().match(/\/node\/(\d+)\/edit/);
  return match ? `/node/${match[1]}` : '';
}

async function assertNewerDraftBanner(page, articlePath) {
  await page.goto(`${base}${articlePath}`, { waitUntil: 'domcontentloaded' });
  const banner = page.locator('.df-editor-newer-draft');
  await banner.waitFor();
  const text = await banner.innerText();
  if (!/newer draft exists/i.test(text)) {
    throw new Error(text);
  }
  const href = await banner.locator('a').getAttribute('href');
  if (!href || !href.includes(`${articlePath}/latest`)) {
    throw new Error(`Latest version link missing: ${href}`);
  }
  console.log('View shows a newer-draft banner');
}

async function assertPreview(page) {
  const errors = [];
  const onError = (err) => errors.push(err.message);
  page.on('pageerror', onError);
  try {
    await page.goto(`${base}/node/add/article`, { waitUntil: 'domcontentloaded' });
    await page.locator('#edit-title-0-value').fill('Playwright preview title');
    await page.locator('#edit-field-byline-0-value').fill('QA Editor');
    await page.locator('#gin-sticky-edit-preview').click();
    await page.waitForURL(/\/node\/preview\//);
    const title = await page.title();
    if (!title.includes('Playwright preview title')) {
      throw new Error(`preview title missing the node title: ${title}`);
    }
    if (errors.some((message) => /dialog/i.test(message))) {
      throw new Error(errors.join('\n'));
    }
    console.log('preview title uses the node title');
  }
  finally {
    page.off('pageerror', onError);
  }
}

async function assertInBriefPreview(page) {
  const errors = [];
  const onError = (err) => errors.push(err.message);
  page.on('pageerror', onError);
  try {
    await page.goto(`${base}/node/add/in_brief`, { waitUntil: 'domcontentloaded' });
    await page.locator('#edit-title-0-value').fill('Playwright in brief preview');
    const summary = page.locator('#edit-field-summary-0-value');
    await summary.waitFor({ state: 'attached' });
    await summary.evaluate((el) => {
      const editor = window.Drupal.CKEditor5Instances.get(el.getAttribute('data-ckeditor5-id'));
      if (editor) {
        editor.setData('<p>Preview summary</p>');
        editor.updateSourceElement();
      }
      else {
        el.value = 'Preview summary';
      }
    });
    await fillIssueReference(page, 'Playwright issue section');
    await page.locator('#gin-sticky-edit-preview').click();
    await page.waitForURL(/\/node\/preview\//);
    const title = await page.title();
    if (!title.includes('Playwright in brief preview')) {
      throw new Error(`in brief preview title missing the node title: ${title}`);
    }
    if (errors.some((message) => /dialog/i.test(message))) {
      throw new Error(errors.join('\n'));
    }
    console.log('in brief preview title uses the node title');
  }
  finally {
    page.off('pageerror', onError);
  }
}

async function assertGalleryErrorScrolls(page) {
  await page.goto(`${base}/node/add/article`, { waitUntil: 'domcontentloaded' });
  await page.locator('#edit-title-0-value').fill(`Playwright gallery ${Date.now()}`);
  await page.locator('#edit-field-byline-0-value').fill('QA Editor');
  await clickParagraphAdd(page, '[name="field_content_sections_image_gallery_add_more"]');
  const nested = page.locator('input[name*="field_gallery_items"][name*="add_more"]').first();
  if (await nested.count()) {
    const ajax = page.waitForResponse((response) => response.request().method() === 'POST' && response.url().includes('ajax_form=1'));
    await clickDrupalAjax(nested);
    await ajax;
  }
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  await page.locator('#gin-sticky-edit-submit').click();
  await page.locator('.df-editor-validation-errors, .df-editor-sticky-errors').first().waitFor();
  await page.waitForFunction(() => {
    const nodes = document.querySelectorAll('.df-editor-validation-errors, .df-editor-inline-error, .df-editor-paragraph-error, .df-editor-sticky-errors');
    return [...nodes].some((target) => {
      const box = target.getBoundingClientRect();
      return box.height > 8 && box.top >= 0 && box.top < window.innerHeight;
    });
  });
  console.log('validation error scrolls into view');
}

async function assertFeaturedImageDialog(page) {
  await page.goto(`${base}/node/add/article`, { waitUntil: 'domcontentloaded' });
  const opener = page.locator('[name="field_featured_image-media-library-open-button"], [data-drupal-selector*="field-featured-image"][data-drupal-selector*="open-button"]').first();
  await opener.waitFor();
  await opener.click();
  await page.locator('.ui-dialog').first().waitFor();
  await clickCoveredSave(page);
  const block = page.locator('.df-editor-media-dialog-block');
  await block.waitFor();
  const text = await block.innerText();
  if (!/still open/i.test(text)) {
    throw new Error(`featured image dialog did not block save: ${text}`);
  }
  if (page.url().includes('/node/') && !page.url().includes('/node/add/')) {
    throw new Error('featured image dialog allowed the node to save');
  }
  console.log('featured image dialog blocks save');
}

async function assertIssueBrief(page) {
  const issueId = process.env.ISSUE_ID;
  if (!issueId) {
    throw new Error('ISSUE_ID is missing');
  }
  await page.goto(`${base}/node/${issueId}`, { waitUntil: 'domcontentloaded' });
  const section = page.locator('article');
  await section.waitFor();
  const text = await section.innerText();
  if (!text.includes('Playwright brief on issue')) {
    throw new Error('published In Brief is missing from the issue page');
  }
  console.log('published In Brief shows on its issue page');
}

async function assertFooterMenu(page) {
  await page.goto(`${base}/about`, { waitUntil: 'domcontentloaded' });
  const list = page.locator('[data-df-footer-source]');
  await list.waitFor();
  const source = await list.getAttribute('data-df-footer-source');
  if (source !== 'menu') {
    throw new Error(`footer links are not rendered from the Footer menu (${source})`);
  }
  const text = await list.innerText();
  for (const label of ['About Impact', 'See All Issues', 'Jimmy Fund Events']) {
    if (!text.includes(label)) {
      throw new Error(`footer menu is missing ${label}: ${text}`);
    }
  }
  console.log('footer links render from the Footer menu');
}

async function assertInBriefSaveMessage(page) {
  const issueId = process.env.ISSUE_ID;
  await page.goto(`${base}/node/${issueId}`, { waitUntil: 'domcontentloaded' });
  const briefHref = await page.getByRole('link', { name: 'Playwright brief on issue' }).first().getAttribute('href');
  await page.goto(new URL(briefHref, base).toString(), { waitUntil: 'domcontentloaded' });
  const href = await page.locator('a[href*="/node/"][href$="/edit"]').first().getAttribute('href');
  await page.goto(new URL(href, base).toString(), { waitUntil: 'domcontentloaded' });
  await page.locator('#gin-sticky-edit-submit').click();
  await page.locator('.messages--status').first().waitFor();
  const text = await page.locator('.messages--status').first().innerText();
  if (!/has been (created|updated)/i.test(text)) {
    throw new Error(`in brief save is missing the status message: ${text}`);
  }
  console.log('in brief save shows a status message');
}

async function assertPublishIssueMessage(page) {
  const publishId = process.env.PUBLISH_ID;
  if (!publishId) {
    throw new Error('PUBLISH_ID is missing');
  }
  await page.goto(`${base}/node/${publishId}/edit`, { waitUntil: 'domcontentloaded' });
  page.once('dialog', (dialog) => dialog.accept());
  await page.locator('.js-df-issue-publish-bundle').click();
  const message = page.locator('.messages--status');
  await message.first().waitFor();
  const text = await message.first().innerText();
  if (!/published issue/i.test(text)) {
    throw new Error(`publish issue is missing the status message: ${text}`);
  }
  console.log('publish issue shows a status message');
}

async function fillIssueReference(page, title) {
  const field = page.locator('[name="field_issue[0][target_id]"]');
  await field.fill(title);
  await page.locator('.ui-autocomplete .ui-menu-item-wrapper').first().waitFor();
  await page.locator('.ui-autocomplete .ui-menu-item-wrapper').first().click();
}

async function assertRevisionHelp(page, articlePath, canRevert) {
  await page.goto(`${base}${articlePath}/revisions`, { waitUntil: 'domcontentloaded' });
  const body = await page.locator('body').innerText();
  const revertLink = page.getByRole('link', { name: 'Revert', exact: true });
  if (canRevert) {
    if (!/\brevert\b/i.test(body) && await revertLink.count() === 0) {
      throw new Error('admin revisions page no longer mentions revert');
    }
    console.log('admin revisions page still offers revert');
    return;
  }
  if (/\brevert\b/i.test(body) || await revertLink.count()) {
    throw new Error('revisions page offers revert without the permission');
  }
  if (!/track differences/i.test(body)) {
    throw new Error('revisions help text is missing');
  }
  console.log('revisions help does not offer revert');
}

async function assertPageSaveMessage(page) {
  await page.goto(`${base}/node/add/page`, { waitUntil: 'domcontentloaded' });
  await page.locator('#edit-title-0-value').fill('Playwright page status');
  await page.locator('#gin-sticky-edit-submit').click();
  await page.waitForURL(/\/node\/\d+(\/latest)?$/);
  const message = page.locator('.messages--status');
  await message.first().waitFor();
  const text = await message.first().innerText();
  if (!/has been created/i.test(text)) {
    throw new Error(`basic page save is missing the status message: ${text}`);
  }
  const box = await message.first().boundingBox();
  if (!box || box.height < 8) {
    throw new Error('basic page status message is not visible');
  }
  console.log('basic page save shows a status message');
}
