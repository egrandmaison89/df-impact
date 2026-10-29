import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { baselinePathForFrozen, frozenWpHtmlPath, normalizePath } from './content-sweep-path.mjs';
import { wpArchiveExportHtmlForPath } from './wp-export-baseline.mjs';
import { issueHasWpArchiveError } from './issue-page-fixture.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.join(__dirname, '..');
const cacheDir = path.join(parityRoot, 'cache/wp-issue-pages');
const packRoot = path.join(parityRoot, 'reference-pack');
const issuesJsonPath = path.join(
  parityRoot,
  '../drupal/web/modules/custom/df_migrate/data/issues.json',
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

/** Integrator gates: no live WP (cache + refpack only). */
export function issueWpCacheOnly() {
  return (
    process.env.PARITY_ISSUE_WP_CACHE_ONLY === '1' || process.env.PARITY_WP_CACHE_ONLY === '1'
  );
}

export function issuePathToCacheFile(publicPath) {
  const slug = normalizePath(publicPath).replace(/^\//, '').replace(/\//g, '_');
  return path.join(cacheDir, `${slug}.html`);
}

function hasIssueOrArchiveGrid(html) {
  return (
    /ultp-post-grid-block|ultp-block-items-wrap|wp-block-query|ultp-block-row/i.test(html) &&
    html.length > 15000
  );
}

export function validateIssueWpHtml(html) {
  if (!html || html.length < 5000 || /sgcaptcha|well-known\/sgcaptcha/i.test(html)) {
    return null;
  }
  if (/Page not found|error404/i.test(html)) {
    return null;
  }
  if (/arch_err/i.test(html) && !/"arch_tag"/i.test(html)) {
    return null;
  }
  if (/"post":"0"/i.test(html) && !hasIssueOrArchiveGrid(html)) {
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
    return validateIssueWpHtml(fs.readFileSync(frozen, 'utf8'));
  }
  const exported = wpArchiveExportHtmlForPath(tagPath);
  return exported ? validateIssueWpHtml(exported) : null;
}

/**
 * issue-cache → tag/refpack fallback → refpack issue slug (no live WP).
 */
export function resolveIssueWpHtml(publicPath) {
  if (issueHasWpArchiveError(publicPath)) {
    const frozen404 = frozenWpHtmlPath(packRoot, publicPath);
    if (frozen404) {
      const html = fs.readFileSync(frozen404, 'utf8');
      return { html, source: 'frozen' };
    }
    return { html: null, source: null };
  }
  const cached = loadIssueWpHtml(publicPath);
  if (cached) {
    return { html: cached, source: 'issue-cache' };
  }
  const tagFallback = loadIssueTagArchiveFallbackHtml(publicPath);
  if (tagFallback) {
    return { html: tagFallback, source: 'issue-tag-frozen' };
  }
  const frozen = frozenWpHtmlPath(packRoot, publicPath);
  if (frozen && fs.existsSync(frozen)) {
    const html = validateIssueWpHtml(fs.readFileSync(frozen, 'utf8'));
    if (html) {
      return { html, source: 'refpack' };
    }
  }
  return { html: null, source: null };
}
