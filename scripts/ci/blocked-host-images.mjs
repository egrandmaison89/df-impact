/**
 * Loads the main pages with WordPress hosts blocked and fails on broken images.
 */
import { createRequire } from 'node:module';

const require = createRequire((process.env.PUPPETEER_CORE_ROOT || '/tmp/df-blocked-host') + '/package.json');
const puppeteer = require('puppeteer-core');

const base = (process.env.BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const chrome = process.env.CHROME_PATH || '/usr/local/bin/google-chrome';
const pages = ['/', '/search?s=cancer', '/browse-issues', '/in-brief', '/late-fall-2022-issue'];

const blockedHost = (hostname) => {
  const host = hostname.toLowerCase();
  return host === 'wp.com' || host.endsWith('.wp.com') || host === 'danafarberimpact.org' || host.endsWith('.danafarberimpact.org');
};

const browser = await puppeteer.launch({
  executablePath: chrome,
  headless: true,
  args: ['--no-sandbox', '--disable-dev-shm-usage'],
});

const failures = [];
try {
  const page = await browser.newPage();
  await page.setRequestInterception(true);
  page.on('request', (request) => {
    try {
      const host = new URL(request.url()).hostname;
      if (blockedHost(host)) {
        request.abort('failed');
        return;
      }
    }
    catch {
      // Non-URL requests (data:) continue.
    }
    request.continue();
  });

  for (const path of pages) {
    const response = await page.goto(base + path, { waitUntil: 'networkidle0', timeout: 45000 });
    const status = response ? response.status() : 0;
    if (status !== 200) {
      failures.push(`${path} status ${status}`);
      continue;
    }
    const broken = await page.evaluate(() => {
      const bad = [];
      for (const img of document.images) {
        const src = img.currentSrc || img.src || '';
        if (!src || src.startsWith('data:')) {
          continue;
        }
        if (!img.complete || img.naturalWidth === 0) {
          bad.push(src);
        }
      }
      return bad;
    });
    if (broken.length > 0) {
      failures.push(`${path} broken images:\n  ${broken.slice(0, 8).join('\n  ')}`);
    }
    else {
      console.log(`images ok ${path}`);
    }
  }
}
finally {
  await browser.close();
}

if (failures.length > 0) {
  console.error(failures.join('\n'));
  process.exit(1);
}
console.log('Blocked-host image check passed.');
