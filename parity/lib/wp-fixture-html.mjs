import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');

export function pathSlug(p) {
  return p.replace(/^\/|\/$/g, '').replace(/\//g, '_') || 'root';
}

export function issueFixturePath(issuePath) {
  const slug = issuePath.replace(/^\/|\/$/g, '').replace(/\//g, '_');
  return path.join(parityRoot, 'cache/wp-issue-pages', `${slug}.html`);
}

export function articleFixturePath(articlePath) {
  return path.join(parityRoot, 'cache/wp-article-variants', `${pathSlug(articlePath)}.html`);
}

export function loadWpFixtureHtml(pagePath) {
  const issueFile = issueFixturePath(pagePath);
  if (fs.existsSync(issueFile)) {
    const html = fs.readFileSync(issueFile, 'utf8');
    if (html.length > 5000 && !/sgcaptcha|well-known\/sgcaptcha/i.test(html)) {
      return { html, source: 'issue-cache', file: issueFile };
    }
  }
  const articleFile = articleFixturePath(pagePath);
  if (fs.existsSync(articleFile)) {
    const html = fs.readFileSync(articleFile, 'utf8');
    if (html.length > 3000) {
      return { html, source: 'article-variant-cache', file: articleFile };
    }
  }
  return null;
}

/** Pass-2 live WP cache baseline: eager images + local wp-images for offline probes. */
export function prepareWpFixtureHtml(html, drupalBase = 'http://127.0.0.1:8888') {
  const localBase = `${drupalBase.replace(/\/$/, '')}/sites/default/files/wp-images`;
  const toLocal = (url) => {
    const raw = (url || '').trim();
    if (!raw || raw.startsWith('data:')) {
      return raw;
    }
    try {
      const normalized = raw.replace(/^https?:\/\/i\d+\.wp\.com\/danafarberimpact\.org/i, 'https://danafarberimpact.org');
      const u = new URL(normalized, 'https://danafarberimpact.org');
      if (!u.pathname.includes('/wp-content/uploads/')) {
        return raw;
      }
      const base = path.basename(u.pathname.split('?')[0]);
      return `${localBase}/${base}`;
    }
    catch {
      return raw;
    }
  };
  return html
    .replace(/\ssrc="([^"]+)"/gi, (full, src) => ` src="${toLocal(src)}"`)
    .replace(/\ssrcset="([^"]+)"/gi, (full, srcset) => {
      const next = srcset
        .split(',')
        .map((part) => {
          const bits = part.trim().split(/\s+/);
          if (bits[0]) {
            bits[0] = toLocal(bits[0]);
          }
          return bits.join(' ');
        })
        .join(', ');
      return ` srcset="${next}"`;
    })
    .replace(/\sdata-src="([^"]+)"/gi, (full, src) => ` data-src="${toLocal(src)}"`)
    .replace(/\sloading="lazy"/gi, ' loading="eager"')
    .replace(/\sclass="([^"]*\blazyload\b[^"]*)"/gi, (match, classes) => {
      const next = classes.replace(/\blazyload\b/g, 'lazyloaded');
      return ` class="${next}"`;
    })
    .replace(/<img([^>]*?)\sdata-src="([^"]+)"([^>]*)>/gi, (full, before, src, after) => {
      if (/\ssrc="/i.test(before + after)) {
        return full;
      }
      return `<img${before} src="${src}" data-src="${src}"${after}>`;
    });
}

/** After setContent on pass-2 WP cache HTML: stylesheets, PostX grid, and images. */
export async function waitForWpFixtureReady(page) {
  await page.waitForLoadState('load').catch(() => {});
  await page
    .waitForFunction(
      () => {
        const priority = document.querySelector('.header-logo img, .desktop-header img, header img');
        const imgs = priority
          ? [priority, ...Array.from(document.images).filter((img) => img !== priority)]
          : Array.from(document.images);
        if (imgs.length === 0) {
          return true;
        }
        return imgs.every((img) => {
          if (!img.src || img.src.startsWith('data:')) {
            return true;
          }
          return img.complete && img.naturalWidth > 0;
        });
      },
      { timeout: 25000 },
    )
    .catch(() => {});
  await page
    .waitForFunction(
      () => {
        const row = document.querySelector('.ultp-block-row.ultp-pg1a-style4');
        if (!row) {
          return true;
        }
        const display = getComputedStyle(row).display;
        return display === 'grid' || display === 'inline-grid';
      },
      { timeout: 20000 },
    )
    .catch(() => {});
  await page.waitForTimeout(200);
}
