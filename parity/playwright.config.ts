import { defineConfig, devices } from '@playwright/test';

const drupalBase = process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888';
const wpBase = process.env.WP_BASE_URL || 'https://danafarberimpact.org';

export default defineConfig({
  testDir: './tests',
  timeout: 120_000,
  expect: { timeout: 15_000 },
  fullyParallel: false,
  workers: 1,
  maxFailures: 0,
  reporter: [['list'], ['json', { outputFile: 'reports/playwright-results.json' }]],
  use: {
    trace: 'off',
    video: 'off',
    actionTimeout: 20_000,
    navigationTimeout: 60_000,
  },
  projects: [
    {
      name: 'behavior',
      testMatch: '**/behavior-parity.spec.ts',
      timeout: 420_000,
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1440, height: 900 },
        navigationTimeout: 180_000,
        video: process.env.PARITY_BEHAVIOR_VIDEO === '1' ? 'on' : 'off',
      },
    },
    {
      name: 'parity',
      testIgnore: ['**/capture-wp-baseline.spec.ts', '**/behavior-parity.spec.ts'],
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1440, height: 900 },
      },
    },
    {
      name: 'capture-baseline',
      testMatch: '**/capture-wp-baseline.spec.ts',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1440, height: 900 },
      },
    },
    {
      name: 'editor-e2e',
      testMatch: '**/editor-e2e.spec.ts',
      timeout: 600_000,
      expect: { timeout: 30_000 },
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1440, height: 900 },
        baseURL: drupalBase,
        actionTimeout: 60_000,
        navigationTimeout: 90_000,
      },
    },
    {
      name: 'admin-e2e',
      testMatch: '**/admin-e2e.spec.ts',
      timeout: 600_000,
      expect: { timeout: 30_000 },
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1440, height: 900 },
        baseURL: drupalBase,
        actionTimeout: 60_000,
        navigationTimeout: 90_000,
      },
    },
  ],
  metadata: { drupalBase, wpBase },
});
