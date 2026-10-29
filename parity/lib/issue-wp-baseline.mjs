import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { baselinePathForFrozen, frozenWpHtmlPath, normalizePath } from './content-sweep-path.mjs';
import { wpArchiveExportHtmlForPath } from './wp-export-baseline.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const cacheDir = path.join(__dirname, '../cache/wp-issue-pages');
const packRoot = path.join(__dirname, '../reference-pack');
const issuesJsonPath = path.join(
  __dirname,
  '../../drupal/web/modules/custom/df_migrate/data/issues.json',
);

/** @type {Map<string, { slug: string }> | null} */
let issueMetaByPath = null;

function issueMetaForPath(publicPath) {
  if (!issueMetaByPath) {
    issueMetaByPath = new Map();
    if (fs.existsSync(issuesJsonPath)) {
      const rows = JSON.parse(fs.readFileSync(issuesJsonPath, 'utf8'));
      if (Array.isArray(rows)) {
        for (const row of rows) {
          const alias = row.path_alias || row.drupal_alias;
          if (typeof alias === 'string' && typeof row.slug === 'string') {
            issueMetaByPath.set(normalizePath(alias), { slug: row.slug });
          }
        }
      }
    }
  }
  return issueMetaByPath.get(normalizePath(publicPath)) ?? null;
}

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
  if (
    /Page not found|error404|arch_err|"post":"0"/i.test(html) &&
    !/ultp-post-grid-block/.test(html)
  ) {
    return null;
  }
  return html;
}

/**
 * WP removed some legacy issue landings; use matching tag archive ref-pack HTML.
 */
export function loadIssueTagArchiveFallbackHtml(publicPath) {
  const meta = issueMetaForPath(publicPath);
  if (!meta) {
    return null;
  }
  const tagPath = `/tag/${meta.slug}`;
  const frozen = frozenWpHtmlPath(packRoot, baselinePathForFrozen(tagPath));
  if (frozen && fs.existsSync(frozen)) {
    return fs.readFileSync(frozen, 'utf8');
  }
  return wpArchiveExportHtmlForPath(tagPath);
}
