import { execSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';

const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const shotDir = path.join(
  process.cwd(),
  'reports',
  'admin-e2e',
  process.env.ADMIN_E2E_RUN_ID || String(Date.now()),
);

const WEB_EDITOR = { name: 'df_test_web_editor', pass: 'test' };
const SITE_ADMIN = { name: 'df_test_site_admin', pass: 'test' };

const TOKEN = `AdminE2E-${Date.now()}`;
const FOOTER_LINK_TITLE = `Jimmy Fund Events ${TOKEN}`;
const SEE_ALL_LABEL = `All Issues ${TOKEN}`;
const DONATE_URL_SUFFIX = `e2e=${TOKEN.replace(/[^a-zA-Z0-9]/g, '')}`;
const HERO_HEADING = `Hero heading ${TOKEN}`;
const HERO_OVERRIDE = `Hero override ${TOKEN}`;

/** df_editor.settings keys mutated by the site_admin UI test. */
const CONFIG_KEYS = [
  'footer_link_jimmy_fund_events_title',
  'donate_url',
  'header_issues_see_all_title',
  'homepage_hero_override_title',
  'homepage_hero_override_link',
] as const;

type ConfigSnapshot = Record<(typeof CONFIG_KEYS)[number], string>;

let configBefore: ConfigSnapshot = {} as ConfigSnapshot;
let heroHeadingBefore = '';
let latestIssueNid = 0;
let snapshotReady = false;

function drush(cmd: string): string {
  return execSync(`cd /workspace/drupal && ./vendor/bin/drush --uri=${drupalBase} ${cmd}`, {
    encoding: 'utf8',
  }).trim();
}

function configGet(key: string): string {
  try {
    return drush(`cget df_editor.settings ${key} --format=string`);
  } catch {
    return '';
  }
}

function configSet(key: string, value: string): void {
  const escaped = value.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
  drush(`cset df_editor.settings ${key} "${escaped}" -y`);
  invalidateIssueChromeCache();
}

function invalidateIssueChromeCache(): void {
  drush(
    `ev '\\Drupal::service("cache_tags.invalidator")->invalidateTags(["node_list:issue","node_list:article","df_editor:issue_navigation","config:df_editor.settings"]);'`,
  );
}

function ensureUsers() {
  execSync(
    'cd /workspace/drupal && ./vendor/bin/drush scr ../parity/scripts/ensure-editor-test-users.php',
    { stdio: 'inherit' },
  );
}

function latestPublishedIssueNid(): number {
  const id = drush("ev 'echo function_exists(\"df_setup_get_latest_issue_nid\") ? df_setup_get_latest_issue_nid() : 0;'");
  return Number(id) || 0;
}

function samplePublishedArticlePath(): string {
  const alias = drush(
    `sqlq "SELECT pa.alias FROM node_field_data n INNER JOIN path_alias pa ON pa.path = CONCAT('/node/', n.nid) WHERE n.type='article' AND n.status=1 AND pa.alias LIKE '/20%' ORDER BY n.nid DESC LIMIT 1"`,
  );
  if (!alias) {
    throw new Error('No published article alias for admin toolbar test');
  }
  return alias.startsWith('/') ? alias : `/${alias}`;
}

async function expectLoggedInToolbar(page: import('@playwright/test').Page) {
  await expect
    .poll(
      async () =>
        page.evaluate(() => {
          const body = document.body;
          return (
            body.classList.contains('user-logged-in') && !body.classList.contains('toolbar-loading')
          );
        }),
      { timeout: 60_000, intervals: [200, 500, 1000] },
    )
    .toBe(true);

  const toolbarBar = page.locator('#toolbar-administration .toolbar-bar');
  await expect(toolbarBar).toHaveCount(1);
  await expect
    .poll(async () => toolbarBar.evaluate((el) => el.getBoundingClientRect().height), {
      timeout: 30_000,
      intervals: [200, 500, 1000],
    })
    .toBeGreaterThan(0);
}

async function expectLoggedInNodeTabs(page: import('@playwright/test').Page) {
  await expectLoggedInToolbar(page);
  await expect(
    page.locator('.df-admin-local-tasks a, nav.tabs-wrapper a, nav.tabs a').filter({ hasText: /^Edit$/i }).first(),
  ).toBeVisible();
}

async function expectStickyHeaderBelowToolbar(page: import('@playwright/test').Page) {
  await page.evaluate(() => window.scrollTo(0, 1200));
  await page.waitForTimeout(300);
  const layout = await page.evaluate(() => {
    const toolbar = document.querySelector('#toolbar-administration');
    const header = document.querySelector('header.wp-block-template-part');
    if (!toolbar || !header) {
      return null;
    }
    const offset = parseFloat(
      getComputedStyle(document.documentElement).getPropertyValue('--drupal-displace-offset-top') || '0',
    );
    const hd = header.getBoundingClientRect();
    return { toolbarBottom: offset, headerTop: hd.top };
  });
  expect(layout).not.toBeNull();
  expect(layout!.headerTop).toBeGreaterThanOrEqual(layout!.toolbarBottom - 2);
}

function assertNoPublishedAutomationNodes(): void {
  const count = Number(
    drush(
      `sqlq "SELECT COUNT(*) FROM node_field_data WHERE status=1 AND (title LIKE 'DF Test%' OR title LIKE 'Playwright%')"`,
    ),
  );
  expect(count, 'published automation test nodes must be cleaned up').toBe(0);
}

/** Restore every setting admin-e2e may change so parity sweeps stay 801/801. */
function restoreChromeSnapshot(): void {
  if (!snapshotReady) {
    return;
  }
  for (const key of CONFIG_KEYS) {
    configSet(key, configBefore[key] ?? '');
  }
  if (latestIssueNid) {
    const heroJson = JSON.stringify(heroHeadingBefore ?? '');
    drush(
      `ev '$n=\\Drupal\\node\\Entity\\Node::load(${latestIssueNid}); if ($n && $n->hasField("field_hero_heading")) { $n->set("field_hero_heading", ${heroJson}); $n->save(); }'`,
    );
  }
  invalidateIssueChromeCache();
}

async function withAnonymousPage(
  page: import('@playwright/test').Page,
  fn: (anon: import('@playwright/test').Page) => Promise<void>,
): Promise<void> {
  const browser = page.context().browser();
  if (!browser) {
    throw new Error('Playwright browser unavailable');
  }
  const ctx = await browser.newContext();
  const anon = await ctx.newPage();
  try {
    await fn(anon);
  } finally {
    await ctx.close();
  }
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
  await page.screenshot({ path: path.join(shotDir, `${name}.png`), fullPage: false });
}

async function saveConfigForm(page: import('@playwright/test').Page) {
  const form = page.locator('form.system-config-form, form[id*="df-editor"]').last();
  await form.locator('#edit-submit, input[type="submit"][value="Save configuration"]').click();
  await expect(page.locator('.messages--status, .messages--error')).toBeVisible({ timeout: 30_000 });
  await expect(page.locator('.messages--error')).toHaveCount(0);
}

async function saveIssueForm(page: import('@playwright/test').Page) {
  const stickySave = page.locator(
    '.region-sticky input[type="submit"][value="Save"], .region-sticky #edit-submit',
  );
  if (await stickySave.count()) {
    await stickySave.first().click();
  } else {
    await page.locator('#edit-submit').click({ force: true });
  }
  const status = page.locator(
    '.messages--status, [data-drupal-messages] .messages--status, [role="contentinfo"]:has-text("Status message")',
  );
  await expect(status.first()).toBeVisible({ timeout: 60_000 });
  await expect(status.first()).toContainText(/updated|saved|has been/i);
  await expect(page.locator('.messages--error')).toHaveCount(0);
}

test.describe('Site admin chrome editing (UI)', () => {
  test.describe.configure({ mode: 'serial' });
  test.setTimeout(600_000);

  test.beforeAll(() => {
    ensureUsers();
    drush('updb -y');
    latestIssueNid = latestPublishedIssueNid();
    expect(latestIssueNid, 'need a published issue for hero edit').toBeGreaterThan(0);
    for (const key of CONFIG_KEYS) {
      configBefore[key] = configGet(key);
    }
    heroHeadingBefore = drush(
      `sqlq "SELECT field_hero_heading_value FROM node__field_hero_heading WHERE entity_id=${latestIssueNid} LIMIT 1"`,
    );
    snapshotReady = true;
    fs.mkdirSync(shotDir, { recursive: true });
  });

  test.afterEach(() => {
    restoreChromeSnapshot();
  });

  test.afterAll(() => {
    restoreChromeSnapshot();
    drush('ev "if (function_exists(\\"df_editor_cleanup_automation_test_nodes\\")) { df_editor_cleanup_automation_test_nodes(); }"');
    assertNoPublishedAutomationNodes();
  });

  test('site_admin front-end toolbar, tabs, and admin/content (anonymous stays clean)', async ({
    page,
  }) => {
    const articlePath = samplePublishedArticlePath();
    await withAnonymousPage(page, async (anon) => {
      await go(anon, '/homepage');
      await expect(anon.locator('#toolbar-administration')).toHaveCount(0);
      await expect(anon.locator('.df-admin-local-tasks')).toHaveCount(0);
      await go(anon, '/fall-2026-issue');
      const bannerHeight = await anon.locator('.issue-page-banner').evaluate((el) => el.getBoundingClientRect().height);
      expect(bannerHeight).toBeGreaterThan(470);
      expect(bannerHeight).toBeLessThan(495);
    });

    await login(page, SITE_ADMIN.name, SITE_ADMIN.pass);
    for (const width of [1280, 768]) {
      await page.setViewportSize({ width, height: 900 });
      await go(page, '/homepage');
      await expectLoggedInToolbar(page);
      await expectStickyHeaderBelowToolbar(page);
    }
    // QA-049: narrow toolbar is not fixed and scrolls away; the header must not keep the gap.
    await page.setViewportSize({ width: 390, height: 900 });
    await go(page, '/homepage');
    await expectLoggedInToolbar(page);
    await page.evaluate(() => window.scrollTo(0, 1200));
    await expect
      .poll(() => page.evaluate(() => Math.round(document.querySelector('header.wp-block-template-part')!.getBoundingClientRect().top)))
      .toBe(0);
    await page.setViewportSize({ width: 1280, height: 900 });
    await go(page, articlePath);
    await expectLoggedInNodeTabs(page);
    const inBriefPath = drush(
      `sqlq "SELECT pa.alias FROM node_field_data n INNER JOIN path_alias pa ON pa.path = CONCAT('/node/', n.nid) WHERE n.type='in_brief' AND n.status=1 ORDER BY n.nid DESC LIMIT 1"`,
    );
    if (inBriefPath) {
      await go(page, inBriefPath.startsWith('/') ? inBriefPath : `/${inBriefPath}`);
      await expectLoggedInNodeTabs(page);
    }
    const adminContent = await go(page, '/admin/content');
    expect(adminContent?.status()).toBe(200);
    await screenshot(page, '00-admin-front-end-toolbar');
    await logout(page);
  });

  test('web_editor cannot access site chrome admin', async ({ page }) => {
    await login(page, WEB_EDITOR.name, WEB_EDITOR.pass);
    for (const route of [
      '/admin/config/df-impact/site-settings',
      '/admin/config/df-impact/footer-settings',
      '/admin/config/df-impact/homepage-hero',
      '/admin/structure/menu/manage/header-primary',
    ]) {
      const res = await go(page, route);
      expect(res?.status(), route).toBe(403);
    }
    await go(page, `/node/${latestIssueNid}/edit`);
    await expect(page.locator('#edit-group-df-homepage-hero')).toHaveCount(0);
    await screenshot(page, '01-web-editor-forbidden');
    await logout(page);
  });

  test('site_admin edits chrome and changes appear on the front end', async ({ page }) => {
    try {
      await login(page, SITE_ADMIN.name, SITE_ADMIN.pass);

      await go(page, '/admin/config/df-impact/footer-settings');
      await page.locator('#edit-footer-link-jimmy-fund-events-title').fill(FOOTER_LINK_TITLE);
      await saveConfigForm(page);

      await go(page, '/admin/config/df-impact/site-settings');
      const donateUrl = await page.locator('#edit-donate-url').inputValue();
      const donateWithMarker = donateUrl.includes('?')
        ? `${donateUrl}&${DONATE_URL_SUFFIX}`
        : `${donateUrl}?${DONATE_URL_SUFFIX}`;
      await page.locator('#edit-donate-url').fill(donateWithMarker);
      await page.locator('summary:has-text("Issues menu")').click();
      await page.locator('#edit-header-issues-see-all-title').fill(SEE_ALL_LABEL);
      await saveConfigForm(page);

      configSet('homepage_hero_override_title', '');
      configSet('homepage_hero_override_link', '');

      await go(page, '/admin/config/df-impact/homepage-hero');
      await expect(page.locator('#edit-homepage-hero-override-title')).toBeVisible();
      await go(page, `/node/${latestIssueNid}/edit`);
      await page.locator('#edit-field-hero-heading-0-value').fill(HERO_HEADING, { force: true });
      await saveIssueForm(page);

      await withAnonymousPage(page, async (anon) => {
        await go(anon, '/');
        await expect
          .poll(async () => anon.locator('.homepage-banner h1').innerText(), {
            timeout: 120_000,
            intervals: [500, 1000, 2000],
          })
          .toContain(HERO_HEADING);
        await expect(anon.locator('.homepage-banner h1 a')).toHaveCount(0);
      });

      await login(page, SITE_ADMIN.name, SITE_ADMIN.pass);
      await go(page, '/admin/config/df-impact/homepage-hero');
      await page.locator('#edit-homepage-hero-override-title').fill(HERO_OVERRIDE);
      await saveConfigForm(page);

      await withAnonymousPage(page, async (anon) => {
        await go(anon, '/');
        await expect
          .poll(async () => anon.locator('.homepage-banner h1').innerText(), {
            timeout: 120_000,
            intervals: [500, 1000, 2000],
          })
          .toContain(HERO_OVERRIDE);
        await expect(anon.locator('.homepage-banner h1')).not.toContainText(HERO_HEADING);

        await expect(anon.locator(`a[href*="donate"], a[href*="Donation"]`).first()).toHaveAttribute(
          'href',
          new RegExp(DONATE_URL_SUFFIX.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')),
        );

        await anon.locator('.mega-menu-link:has-text("Issues")').first().click();
        await expect(anon.locator(`.mega-menu-link:has-text("${SEE_ALL_LABEL}")`).first()).toBeVisible();

        await go(anon, '/');
        await expect(anon.locator('footer')).toContainText(FOOTER_LINK_TITLE);
      });
      await screenshot(page, '03-front-end-chrome');
    } finally {
      restoreChromeSnapshot();
    }
  });
});
