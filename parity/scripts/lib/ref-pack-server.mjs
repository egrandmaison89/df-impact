import fs from 'node:fs';
import http from 'node:http';
import path from 'node:path';

/**
 * Local HTTP server: rewritten pack rendered.html + vendored assets (Linux Chromium reference).
 */
export function loadAssetsIndex(packRoot) {
  return JSON.parse(fs.readFileSync(path.join(packRoot, 'assets-index.json'), 'utf8'));
}

export function rewriteRenderedHtml(html, assetIndex, assetBaseUrl, options = {}) {
  let out = html;
  const drupal = options.drupalBase?.replace(/\/$/, '');
  if (drupal) {
    const themeLogo = `${drupal}/themes/custom/df_impact/wp-assets/ImpactDFJF_HeaderLogo_878.png`;
    out = out.replace(
      /https:\/\/(?:i\d+\.wp\.com\/)?danafarberimpact\.org\/wp-content\/uploads\/2023\/03\/ImpactDFJF_HeaderLogo_878\.png[^"'\\s]*/g,
      themeLogo,
    );
    out = out.replace(
      /https:\/\/(?:i\d+\.wp\.com\/)?danafarberimpact\.org\/wp-content\//g,
      `${drupal}/wp-content/`,
    );
  }
  const entries = Object.entries(assetIndex).sort((a, b) => b[0].length - a[0].length);
  for (const [rawUrl, meta] of entries) {
    const file = meta.file.replace(/^assets\//, '');
    const local = `${assetBaseUrl}/${file}`;
    out = out.split(rawUrl).join(local);
    const noQuery = rawUrl.split('?')[0];
    if (noQuery !== rawUrl) {
      out = out.split(noQuery).join(local);
    }
  }
  // Protocol-relative URLs in captured HTML.
  out = out.replace(/\/\/c0\.wp\.com\//g, 'https://c0.wp.com/');
  out = out.replace(/\/\/use\.fontawesome\.com\//g, 'https://use.fontawesome.com/');
  out = out.replace(/\/\/i0\.wp\.com\//g, 'https://i0.wp.com/');
  return out;
}

export function startRefPackServer(packRoot, options = {}) {
  const assetIndex = loadAssetsIndex(packRoot);
  const assetsDir = path.join(packRoot, 'assets');
  const serverOptions = options;

  const server = http.createServer((req, res) => {
    try {
      const u = new URL(req.url || '/', 'http://127.0.0.1');
      if (u.pathname.startsWith('/assets/')) {
        const rel = decodeURIComponent(u.pathname.slice('/assets/'.length));
        const filePath = path.join(assetsDir, rel);
        if (!filePath.startsWith(assetsDir) || !fs.existsSync(filePath)) {
          res.writeHead(404);
          res.end('not found');
          return;
        }
        res.writeHead(200);
        fs.createReadStream(filePath).pipe(res);
        return;
      }
      const m = u.pathname.match(/^\/rendered\/([^/]+)\/(desktop|tablet|mobile)\/?$/);
      if (m) {
        const htmlPath = path.join(packRoot, m[1], m[2], 'rendered.html');
        if (!fs.existsSync(htmlPath)) {
          res.writeHead(404);
          res.end('no rendered.html');
          return;
        }
        const raw = fs.readFileSync(htmlPath, 'utf8');
        const port = server.address()?.port || 0;
        const assetBase = `http://127.0.0.1:${port}/assets`;
        const body = rewriteRenderedHtml(raw, assetIndex, assetBase, serverOptions);
        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end(body);
        return;
      }
      res.writeHead(404);
      res.end('not found');
    } catch (e) {
      res.writeHead(500);
      res.end(String(e));
    }
  });

  return new Promise((resolve, reject) => {
    server.listen(0, '127.0.0.1', () => {
      const { port } = server.address();
      resolve({
        baseUrl: `http://127.0.0.1:${port}`,
        close: () =>
          new Promise((r) => {
            server.close(() => r());
          }),
      });
    });
    server.on('error', reject);
  });
}
