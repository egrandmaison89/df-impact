import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { frozenWpHtmlPath, normalizePath } from './content-sweep-path.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.join(__dirname, '..');
const cacheDir = path.join(parityRoot, 'cache/wp-issue-pages');
const packRoot = path.join(parityRoot, 'reference-pack');

/** Integrator gates: no live WP (cache + refpack only). */
export function issueWpCacheOnly() {
  return (
    process.env.PARITY_ISSUE_WP_CACHE_ONLY === '1'
    || process.env.PARITY_WP_CACHE_ONLY === '1'
  );
}

export function issuePathToCacheFile(publicPath) {
  const slug = normalizePath(publicPath).replace(/^\//, '').replace(/\//g, '_');
  return path.join(cacheDir, `${slug}.html`);
}

export function validateIssueWpHtml(html) {
  if (!html || html.length < 5000 || /sgcaptcha|well-known\/sgcaptcha/i.test(html)) {
    return null;
  }
  if (/arch_err|"post":"0"/.test(html) && !/ultp-post-grid-block/.test(html)) {
    return null;
  }
  return html;
}

export function loadIssueWpHtml(publicPath) {
  const file = issuePathToCacheFile(publicPath);
  if (!fs.existsSync(file)) {
    return null;
  }
  return validateIssueWpHtml(fs.readFileSync(file, 'utf8'));
}

/**
 * issue-cache → refpack desktop rendered (no live WP).
 */
export function resolveIssueWpHtml(publicPath) {
  const cached = loadIssueWpHtml(publicPath);
  if (cached) {
    return { html: cached, source: 'issue-cache' };
  }
  const frozen = frozenWpHtmlPath(packRoot, publicPath);
  if (frozen) {
    const html = validateIssueWpHtml(fs.readFileSync(frozen, 'utf8'));
    if (html) {
      return { html, source: 'refpack' };
    }
  }
  return { html: null, source: null };
}
