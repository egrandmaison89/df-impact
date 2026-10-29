import { execSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';

const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const shotDir = path.join(
  process.cwd(),
  'reports',
  'editor-e2e',
  process.env.EDITOR_E2E_RUN_ID || String(Date.now()),
);

const WEB_EDITOR = { name: 'df_test_web_editor', pass: 'test' };
const SITE_ADMIN = { name: 'df_test_site_admin', pass: 'test' };

const TITLE_PREFIX = 'Playwright Winter';
const YEAR = 2040;
const ISSUE_TITLE = `${TITLE_PREFIX} ${YEAR} Issue`;
const HERO_HEADING = `${TITLE_PREFIX} ${YEAR} Hero`;
const ARTICLE_TITLES = [
  `${TITLE_PREFIX} ${YEAR} UI Article 1`,
  `${TITLE_PREFIX} ${YEAR} UI Article 2`,
  `${TITLE_PREFIX} ${YEAR} UI Article 3`,
];

let issueNid: number | null = null;
const articleNids: number[] = [];

function drush(cmd: string): string {
  return execSync(`cd /workspace/drupal && ./vendor/bin/drush --uri=${drupalBase} ${cmd}`, {
    encoding: 'utf8',
  }).trim();
}

function ensureUsers() {
  execSync(
    'cd /workspace/drupal && ./vendor/bin/drush scr ../parity/scripts/ensure-editor-test-users.php',
    { stdio: 'inherit' },
  );
}

function cleanupPriorRunContent() {
  execSync(
    `cd /workspace/drupal && ./vendor/bin/drush scr ../parity/scripts/cleanup-editor-e2e-nodes.php -- '${TITLE_PREFIX}' && ./vendor/bin/drush --uri=${drupalBase} cr`,
    { stdio: 'inherit' },
  );
}

/** After long sweeps or admin-e2e, warm PHP, cache, and common editor routes. */
function warmEnvironmentAfterGate() {
  drush('cr');
  for (const path of ['/', '/user/login', '/node/add/issue', '/node/add/article']) {
    execSync(`curl -sS -o /dev/null -w "" ${drupalBase}${path}`, { stdio: 'pipe' });
  }
}

/** admin-e2e leaves override config restored, but verify so homepage hero assertions stay stable. */
function ensureHomepageHeroOverrideClear() {
  const override = drush('cget df_editor.settings homepage_hero_override_title --format=string');
  if (override.trim() !== '') {
    drush('cset df_editor.settings homepage_hero_override_title "" -y');
    drush('cset df_editor.settings homepage_hero_override_link "" -y');
    drush('cr');
  }
}

function latestPublishedIssueNid(): number {
  const id = drush("ev 'echo function_exists(\"df_setup_get_latest_issue_nid\") ? df_setup_get_latest_issue_nid() : 0;'");
  return Number(id) || 0;
}

function nodeNidByTitle(title: string): number {
  const safe = title.replace(/'/g, "''");
  const id = execSync(
    `cd /workspace/drupal && ./vendor/bin/drush sqlq "SELECT nid FROM node_field_data WHERE title='${safe}' ORDER BY nid DESC LIMIT 1"`,
    { encoding: 'utf8' },
  ).trim();
  return Number(id);
}

/** Site admins set hero heading; web editors cannot see the field in the UI. */
function setIssueHeroHeading(nid: number, heading: string): void {
  const safe = heading.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
  execSync(
    `cd /workspace/drupal && ./vendor/bin/drush --uri=${drupalBase} ev "\\$n = \\\\Drupal\\\\node\\\\Entity\\\\Node::load(${nid}); if (\\$n) { \\$n->set('field_hero_heading', '${safe}'); \\$n->save(); }"`,
    { stdio: 'inherit' },
  );
}

function cleanupNodes() {
  const nids = [...(issueNid ? [issueNid] : []), ...articleNids];
  if (!nids.length) return;
  execSync(
    `cd /workspace/drupal && ./vendor/bin/drush ev 'foreach ([${nids.join(',')}] as $nid) { \\Drupal\\node\\Entity\\Node::load($nid)?->delete(); }' && ./vendor/bin/drush cr`,
    { stdio: 'inherit' },
  );
}

async function go(page: import('@playwright/test').Page, route: string) {
  const url = route.startsWith('http')
    ? route
    : `${drupalBase}${route.startsWith('/') ? route : `/${route}`}`;
  return page.goto(url, { waitUntil: 'domcontentloaded' });
}

async function logout(page: import('@playwright/test').Page) {
  await page.context().clearCookies();
  await go(page, '/user/login');
}

async function login(page: import('@playwright/test').Page, name: string, pass: string) {
  await logout(page);
  const userField = page.locator('#edit-name, input[name="name"]').first();
  await userField.waitFor({ state: 'visible', timeout: 60_000 });
  await userField.fill(name);
  await page.locator('#edit-pass, input[name="pass"]').first().fill(pass);
  await page.locator('#edit-submit, input[type="submit"][name="op"]').first().click();
  await expect(page.locator('body')).not.toContainText('Username or password');
}

async function screenshot(page: import('@playwright/test').Page, name: string) {
  fs.mkdirSync(shotDir, { recursive: true });
  await page.screenshot({ path: path.join(shotDir, `${name}.png`), fullPage: true });
}

async function featuredImageTargetId(page: import('@playwright/test').Page): Promise<string> {
  const selection = page.locator('input[name="field_featured_image[media_library_selection]"]');
  if (await selection.count()) {
    return selection.inputValue();
  }
  return page.locator('input[name="field_featured_image[0][target_id]"]').inputValue();
}

async function waitForFeaturedImage(page: import('@playwright/test').Page) {
  await expect
    .poll(async () => (await featuredImageTargetId(page)).trim(), { timeout: 60_000 })
    .not.toBe('');
  await expect(
    page.locator('.field--name-field-featured-image').getByText('No media items are selected'),
  ).toHaveCount(0);
}

/** Pick the first item in a Drupal media-library modal (Cover / Banner / Featured). */
async function addMediaFromLibrary(page: import('@playwright/test').Page, openButtonId: string) {
  for (let attempt = 0; attempt < 3; attempt++) {
    const openBtn = page.locator(`#${openButtonId}`);
    await openBtn.waitFor({ state: 'attached', timeout: 120_000 });
    await openBtn.scrollIntoViewIfNeeded();
    await openBtn.dispatchEvent('mousedown');
    const dialog = page.locator('.ui-dialog.media-library-widget-modal');
    const dialogTimeout = attempt === 0 ? 45_000 : 20_000;
    try {
      await dialog.waitFor({ state: 'visible', timeout: dialogTimeout });
    } catch {
      await openBtn.click({ force: true });
      await dialog.waitFor({ state: 'visible', timeout: dialogTimeout });
    }
    await dialog.locator('.js-media-library-item').first().waitFor({ state: 'visible', timeout: 60_000 });
    const firstItem = dialog.locator('.js-media-library-item input[type="checkbox"]').first();
    await firstItem.check({ force: true });
    const insert = dialog.getByRole('button', { name: /Insert selected/i });
    await Promise.all([
      page
        .waitForResponse((r) => r.url().includes('media-library') && r.status() < 400, { timeout: 60_000 })
        .catch(() => null),
      insert.click(),
    ]);
    await dialog.waitFor({ state: 'hidden', timeout: 60_000 });
    if (openButtonId.includes('featured-image')) {
      await expect
        .poll(async () => (await featuredImageTargetId(page)).trim(), { timeout: 90_000 })
        .not.toBe('');
      return;
    }
    return;
  }
  throw new Error(`Media library did not attach for #${openButtonId}`);
}

/** Gin admin puts Save in a sticky header that blocks the footer submit button. */
async function nodeNidFromPage(
  page: import('@playwright/test').Page,
  title: string,
): Promise<number> {
  const fromUrl = page.url().match(/\/node\/(\d+)/)?.[1];
  if (fromUrl) return Number(fromUrl);
  const editHref = await page
    .locator('a[href*="/node/"][href*="/edit"], .tabs a[href*="/node/"]')
    .first()
    .getAttribute('href')
    .catch(() => null);
  const fromEdit = editHref?.match(/\/node\/(\d+)/)?.[1];
  if (fromEdit) return Number(fromEdit);
  const fromDb = nodeNidByTitle(title);
  if (fromDb) return fromDb;
  throw new Error(`Could not resolve node ID for ${title} from ${page.url()}`);
}

const FORM_TAB_GROUP: Record<string, string> = {
  Content: 'content',
  'Publication & placement': 'metadata',
  'SEO & Summary': 'seo',
};

async function expandArticleFieldGroups(page: import('@playwright/test').Page) {
  for (const label of ['Content', 'Publication & placement', 'SEO & Summary']) {
    const summary = page.locator(`details.field-group-tab summary, details summary`).filter({ hasText: label });
    if (await summary.count()) {
      await summary.first().click();
    }
  }
}

async function openFormTab(page: import('@playwright/test').Page, label: string) {
  const group = FORM_TAB_GROUP[label] ?? label.toLowerCase().replace(/[^a-z0-9]+/g, '-');
  const details = page.locator(
    `#edit-group-${group}, details[data-drupal-selector="edit-group-${group}"]`,
  );
  if (await details.count()) {
    const panel = details.first();
    const open = await panel.evaluate((el) => (el as HTMLDetailsElement).open);
    if (!open) {
      await panel.locator('summary').first().click();
    }
    await expect(panel).toHaveAttribute('open', '', { timeout: 30_000 });
    return;
  }
  const anchor = page.locator(
    `a[href="#edit-group-${group}"], .horizontal-tab-button a:has-text("${label}")`,
  ).first();
  if (await anchor.count()) {
    await anchor.click();
    await page.locator(`#edit-group-${group}`).waitFor({ state: 'attached', timeout: 30_000 });
    return;
  }
  const tab = page.getByRole('tab', { name: label });
  if (await tab.count()) {
    await tab.click();
  }
}

async function fillArticleBody(page: import('@playwright/test').Page, html: string) {
  const plain = html.replace(/<[^>]+>/g, '');
  const editable = page
    .locator('[data-drupal-selector="edit-body-0"] .ck-editor__editable, .field--name-body .ck-editor__editable')
    .first();
  await editable.click({ force: true });
  await page.keyboard.press(`${process.platform === 'darwin' ? 'Meta' : 'Control'}+A`);
  await page.keyboard.type(plain);
}

async function pickAutocompleteOption(page: import('@playwright/test').Page, field: import('@playwright/test').Locator, text: string) {
  await field.click({ force: true });
  await field.fill('', { force: true });
  await field.fill(text, { force: true });
  const option = page.locator('.ui-autocomplete li.ui-menu-item').filter({ hasText: text }).first();
  try {
    await option.waitFor({ state: 'visible', timeout: 8000 });
    await option.click();
  } catch {
    // Some references (draft issues) do not appear in AJAX suggestions; keep the typed label (nid) value.
  }
  await field.press('Tab');
}

function topicTermId(): number {
  const id = execSync(
    `cd /workspace/drupal && ./vendor/bin/drush sqlq "SELECT tid FROM taxonomy_term_field_data WHERE name='Cancer Research' LIMIT 1"`,
    { encoding: 'utf8' },
  ).trim();
  return Number(id);
}

async function fillArticleFieldsViaDom(
  page: import('@playwright/test').Page,
  opts: {
    title: string;
    issueTitle: string;
    issueNid: number;
    excerpt: string;
    topicLabel: string;
  },
) {
  await page.evaluate(
    ({ title, body, byline, excerpt, issueTitle, issueNid, topicLabel }) => {
      const set = (selector: string, value: string) => {
        const el = document.querySelector(selector) as HTMLInputElement | HTMLTextAreaElement | null;
        if (!el) return;
        el.value = value;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
      };
      set('#edit-title-0-value', title);
      set('#edit-field-byline-0-value', byline);
      set('#edit-field-excerpt-0-value', excerpt);
      set('#edit-body-0-value', body);
    },
    {
      title: opts.title,
      body: `<p>Body for ${opts.title}</p>`,
      byline: 'Playwright Author',
      excerpt: opts.excerpt,
      issueTitle: opts.issueTitle,
      issueNid: opts.issueNid,
      topicLabel: opts.topicLabel,
    },
  );
}

async function setIssueReference(page: import('@playwright/test').Page, title: string, nid: number) {
  const value = `${title} (${nid})`;
  await page.evaluate(
    ({ selector, v }) => {
      const el = document.querySelector(selector) as HTMLInputElement | null;
      if (el) {
        el.value = v;
        el.dispatchEvent(new Event('change', { bubbles: true }));
      }
    },
    { selector: '#edit-field-issue-0-target-id', v: value },
  );
}

async function setTopicsReference(page: import('@playwright/test').Page, topicLabel: string) {
  await page.evaluate(
    ({ selector, v }) => {
      const el = document.querySelector(selector) as HTMLInputElement | null;
      if (el) {
        el.value = v;
        el.dispatchEvent(new Event('change', { bubbles: true }));
      }
    },
    { selector: '#edit-field-topics-target-id', v: topicLabel },
  );
}

async function saveNodeForm(page: import('@playwright/test').Page, opts?: { savedTitle?: string }) {
  const stickySave = page.locator(
    '.region-sticky input[type="submit"][value="Save"], .region-sticky #edit-submit',
  );
  const submit = (await stickySave.count()) ? stickySave.first() : page.locator('#edit-submit');
  await submit.click();
  await expect
    .poll(
      async () => {
        if ((await page.locator('.messages--error').count()) > 0) {
          return 'error';
        }
        if ((await page.locator('.messages--status').count()) > 0) {
          return 'ok';
        }
        if (/\/node\/\d+/.test(page.url())) {
          return 'ok';
        }
        if (opts?.savedTitle && nodeNidByTitle(opts.savedTitle) > 0) {
          return 'ok';
        }
        return '';
      },
      { timeout: 120_000, intervals: [250, 500, 1000] },
    )
    .toBe('ok');
  if ((await page.locator('.messages--error').count()) > 0) {
    throw new Error(`Node save failed: ${await page.locator('.messages--error').innerText()}`);
  }
}

async function waitForIssueAddForm(page: import('@playwright/test').Page) {
  await page.locator('#edit-title-0-value').waitFor({ state: 'visible', timeout: 120_000 });
  await page.locator('#edit-field-cover-image-open-button').waitFor({ state: 'attached', timeout: 120_000 });
  await page.locator('#edit-field-banner-image-open-button').waitFor({ state: 'attached', timeout: 120_000 });
}

async function waitForArticleAddForm(page: import('@playwright/test').Page) {
  await page.locator('body').waitFor({ state: 'visible', timeout: 120_000 });
  await expect
    .poll(
      async () => {
        await openFormTab(page, 'Content');
        const btn = page.locator('#edit-field-featured-image-open-button');
        return btn.isVisible();
      },
      { timeout: 120_000, intervals: [500, 1000, 2000] },
    )
    .toBe(true);
}

async function expectLatestIssueIs(nid: number) {
  await expect
    .poll(() => latestPublishedIssueNid(), { timeout: 120_000, intervals: [500, 1000, 2000] })
    .toBe(nid);
}

async function expectPublicHomepageHero(page: import('@playwright/test').Page, heading: string) {
  await expectLatestIssueIs(issueNid!);
  drush('cr');
  await page.goto(`${drupalBase}/?editor_e2e=${Date.now()}`, {
    waitUntil: 'domcontentloaded',
    timeout: 120_000,
  });
  await expect
    .poll(async () => page.locator('.homepage-banner h1').innerText(), {
      timeout: 120_000,
      intervals: [500, 1000, 2000],
    })
    .toContain(heading);
}

test.describe('Web editor issue launch (UI)', () => {
  test.describe.configure({ mode: 'serial' });
  test.setTimeout(600_000);

  test.beforeAll(() => {
    ensureUsers();
    cleanupPriorRunContent();
    ensureHomepageHeroOverrideClear();
    warmEnvironmentAfterGate();
    fs.mkdirSync(shotDir, { recursive: true });
  });

  test.beforeEach(() => {
    execSync(`curl -sS -o /dev/null ${drupalBase}/user/login`, { stdio: 'pipe' });
  });

  test.afterAll(() => {
    cleanupNodes();
  });

  test('RBAC: web_editor denied site chrome; site_admin allowed', async ({ page }) => {
    await login(page, WEB_EDITOR.name, WEB_EDITOR.pass);
    for (const route of [
      '/admin/config/df-impact/site-settings',
      '/admin/config/df-impact/footer-settings',
      '/admin/config/df-impact/homepage-hero',
    ]) {
      const res = await go(page, route);
      expect(res?.status(), route).toBe(403);
    }
    await screenshot(page, '01-web-editor-forbidden-settings');

    await logout(page);
    await login(page, SITE_ADMIN.name, SITE_ADMIN.pass);
    for (const route of [
      '/admin/config/df-impact/site-settings',
      '/admin/config/df-impact/footer-settings',
      '/admin/config/df-impact/homepage-hero',
    ]) {
      const res = await go(page, route);
      expect(res?.status(), route).toBe(200);
    }
    await screenshot(page, '02-site-admin-settings-ok');
    await logout(page);
  });

  test('Create issue + 3 articles and publish from Issue dashboard', async ({ page }) => {
    await login(page, WEB_EDITOR.name, WEB_EDITOR.pass);

    await go(page, '/node/add/issue');
    await expect(page.locator('body')).toContainText('Create Issue');
    await waitForIssueAddForm(page);
    await page.locator('#edit-title-0-value').fill(ISSUE_TITLE);
    await page.locator('#edit-field-season').selectOption('winter');
    await page.locator('#edit-field-year-0-value').fill(String(YEAR));
    await expect(page.locator('#edit-field-hero-heading-0-value')).toHaveCount(0);
    await screenshot(page, '03-issue-form-filled');

    await addMediaFromLibrary(page, 'edit-field-cover-image-open-button');
    await addMediaFromLibrary(page, 'edit-field-banner-image-open-button');
    await screenshot(page, '03b-issue-media-selected');
    await saveNodeForm(page, { savedTitle: ISSUE_TITLE });
    let savedIssueNid = 0;
    await expect
      .poll(() => {
        savedIssueNid = nodeNidByTitle(ISSUE_TITLE);
        return savedIssueNid;
      }, { timeout: 60_000 })
      .toBeGreaterThan(0);
    issueNid = savedIssueNid;
    setIssueHeroHeading(issueNid, HERO_HEADING);
    await screenshot(page, '04-issue-saved-draft');

    const topicLabel = `Cancer Research (${topicTermId()})`;
    for (const title of ARTICLE_TITLES) {
      await go(page, '/node/add/article');
      await expect(page.locator('body')).toContainText('Create Article');
      await waitForArticleAddForm(page);
      await page.locator('#edit-title-0-value').fill(title);
      await addMediaFromLibrary(page, 'edit-field-featured-image-open-button');
      await waitForFeaturedImage(page);
      await fillArticleFieldsViaDom(page, {
        title,
        issueTitle: ISSUE_TITLE,
        issueNid: issueNid!,
        excerpt: `Excerpt for ${title}`,
        topicLabel,
      });
      await openFormTab(page, 'Publication & placement');
      await setIssueReference(page, ISSUE_TITLE, issueNid!);
      await setTopicsReference(page, topicLabel);
      await openFormTab(page, 'SEO & Summary');
      await saveNodeForm(page, { savedTitle: title });
      await screenshot(page, `05-article-saved-${articleNids.length + 1}`);
      const inlineErrors = page.locator('.form-item--error-message');
      await expect(inlineErrors).toHaveCount(0);
      let articleNid = 0;
      await expect
        .poll(() => {
          articleNid = nodeNidByTitle(title);
          return articleNid;
        }, { timeout: 60_000 })
        .toBeGreaterThan(0);
      articleNids.push(articleNid);
      await page.waitForLoadState('domcontentloaded');
    }
    await screenshot(page, '05-articles-created');

    await go(page, `/admin/content/issue-dashboard?issue=${issueNid}`);
    await expect(page.locator('body')).toContainText(ISSUE_TITLE);
    await expect(page.locator('body')).toContainText(ARTICLE_TITLES[0]);
    await screenshot(page, '06-issue-dashboard-before-publish');

    await page.locator('form.df-issue-dashboard-publish input[type="submit"], form.df-issue-dashboard-publish button').first().click();
    const confirm = page.locator(
      'form.confirmation input#edit-submit, form.confirmation button[type="submit"], .ui-dialog-buttonset button.button--primary',
    );
    if (await confirm.count()) {
      await confirm.first().click();
    }
    await expect(page.locator('.messages--status')).toContainText('Published', { timeout: 60_000 });
    await screenshot(page, '07-after-publish');
    const issuePublished = execSync(
      `cd /workspace/drupal && ./vendor/bin/drush sqlq "SELECT status FROM node_field_data WHERE nid=${issueNid} LIMIT 1"`,
      { encoding: 'utf8' },
    ).trim();
    expect(issuePublished).toBe('1');
    const heroInDb = execSync(
      `cd /workspace/drupal && ./vendor/bin/drush sqlq "SELECT field_hero_heading_value FROM node__field_hero_heading WHERE entity_id=${issueNid} LIMIT 1"`,
      { encoding: 'utf8' },
    ).trim();
    expect(heroInDb).toBe(HERO_HEADING);

    await go(page, '/admin/content/issue-dashboard/clear-preview');
    drush('cr');
    await expectLatestIssueIs(issueNid!);

    const issuePath =
      execSync(
        `cd /workspace/drupal && ./vendor/bin/drush sqlq "SELECT alias FROM path_alias WHERE path = '/node/${issueNid}' LIMIT 1"`,
        { encoding: 'utf8' },
      ).trim() || `/node/${issueNid}`;

    await logout(page);
    await expect
      .poll(
        () =>
          execSync(`curl -sL -o /dev/null -w '%{http_code}' ${drupalBase}/`, { encoding: 'utf8' }).trim(),
        { timeout: 60_000 },
      )
      .toBe('200');

    await expectPublicHomepageHero(page, HERO_HEADING);
    await expect(page.locator('body')).toContainText('Explore the Latest Issue');
    for (const t of ARTICLE_TITLES) {
      await expect(page.locator('body')).toContainText(t);
    }
    await screenshot(page, '08-homepage-after-publish');

    const header = page.locator('header, .wp-block-template-part, nav').first();
    await expect(header).toContainText(/Issues|Winter/i);
    await expect(header).toContainText(String(YEAR));
    await screenshot(page, '09-header-issues-nav');

    await go(page, '/browse-issues');
    await expect(page.locator('body')).toContainText(`Winter ${YEAR}`);
    await screenshot(page, '10-browse-issues');

    await go(page, issuePath);
    for (const t of ARTICLE_TITLES) {
      await expect(page.locator('body')).toContainText(t);
    }
    await screenshot(page, '11-issue-landing');
  });
});
