import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { normalizePath } from './content-sweep-path.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const cacheDir = path.join(__dirname, '../cache/wp-issue-pages');

export function issuePathToCacheFile(publicPath) {
  const slug = normalizePath(publicPath).replace(/^\//, '').replace(/\//g, '_');
  return path.join(cacheDir, `${slug}.html`);
}

export function loadIssueWpHtml(publicPath) {
  const file = issuePathToCacheFile(publicPath);
  if (!fs.existsSync(file)) {
    return null;
  }
  const html = fs.readFileSync(file, 'utf8');
  if (html.length < 5000 || /sgcaptcha|well-known\/sgcaptcha/i.test(html)) {
    return null;
  }
  if (/arch_err|"post":"0"/.test(html) && !/ultp-post-grid-block/.test(html)) {
    return null;
  }
  return html;
}
