import http from 'node:http';
import https from 'node:https';

/**
 * Minimal GET (avoids undici fetch crashes under heavy parallel use).
 */
export function httpGet(url, { timeoutMs = 45000, headers = {}, maxRedirects = 5 } = {}) {
  return new Promise((resolve, reject) => {
    const visit = (currentUrl, redirectsLeft) => {
      const lib = currentUrl.startsWith('https') ? https : http;
      const req = lib.get(
        currentUrl,
        {
          headers: {
            'User-Agent': 'df-impact-content-sweep/1.0',
            Accept: 'text/html',
            ...headers,
          },
          timeout: timeoutMs,
        },
        (res) => {
          const status = res.statusCode ?? 0;
          if (
            redirectsLeft > 0 &&
            status >= 300 &&
            status < 400 &&
            res.headers.location
          ) {
            res.resume();
            const next = new URL(res.headers.location, currentUrl).toString();
            visit(next, redirectsLeft - 1);
            return;
          }
          const chunks = [];
          res.on('data', (c) => chunks.push(c));
          res.on('end', () => {
            resolve({
              status,
              headers: res.headers,
              body: Buffer.concat(chunks).toString('utf8'),
              url: currentUrl,
            });
          });
        },
      );
      req.on('error', reject);
      req.on('timeout', () => {
        req.destroy();
        reject(new Error(`timeout ${currentUrl}`));
      });
    };
    visit(url, maxRedirects);
  });
}
