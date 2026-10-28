import { defineConfig, devices } from '@playwright/test';

const drupalBase = process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888';
const wpBase = process.env.WP_BASE_URL || 'https://danafarberimpact.org';

export default defineConfig({
  testDir: './tests',
  timeout: 120_000,
  expect: { timeout: 15_000 },
  fullyParallel: false,
  workers: 1,
  reporter: [['list'], ['json', { outputFile: 'reports/playwright-results.json' }]],
  use: {
    trace: 'off',
    video: 'off',
    actionTimeout: 20_000,
    navigationTimeout: 60_000,
  },
  projects: [
    {
      name: 'parity',
      testIgnore: '**/capture-wp-baseline.spec.ts',
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
  ],
  metadata: { drupalBase, wpBase },
});
