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

/** Restore every setting admin-e2e may change so parity sweeps stay 801/801. */
function restoreChromeSnapshot(): void {
  if (!snapshotReady) {
    return;
  }
  for (const key of CONFIG_KEYS) {
    configSet(key, configBefore[key] ?? '');
  }
  if (latestIssueNid && heroHeadingBefore !== undefined) {
    const safe = heroHeadingBefore.replace(/'/g, "''");
    drush(
      `sqlq "UPDATE node__field_hero_heading SET field_hero_heading_value='${safe}' WHERE entity_id=${latestIssueNid}"`,
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
