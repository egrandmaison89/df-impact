/** Shared manifest URL loading for parity and baseline capture. */
import fs from 'node:fs';
import path from 'node:path';

/** Playwright runs with cwd=`parity/`; bootstrap scripts use the same. */
export const repoRoot = path.resolve(process.cwd(), '..');
export const manifestPath = path.join(process.cwd(), 'manifest', 'site-manifest.json');

/** HTML/content URLs in scope for visual + same-path parity (excludes media binaries). */
export function isContentParityUrl(entry) {
  const p = (entry.path || '').toLowerCase();
  const tpl = entry.template || 'other';
  if (tpl === 'media' || tpl === 'document') return false;
  if (/wp-content\/uploads/.test(p)) return false;
  if (p.endsWith('/feed') || p.endsWith('.pdf') || p.endsWith('.jpg') || p.endsWith('.png')) return false;
  if (p.includes('danafarberimpact.org/')) return false;
  return true;
}

export function normalizePath(p) {
  const x = p.startsWith('/') ? p : `/${p}`;
  const trimmed = x.replace(/\/+$/, '');
  return trimmed || '/';
}

export function publicPath(entry) {
  if (entry.url) {
    try {
      return normalizePath(new URL(entry.url).pathname);
    } catch {
      /* fall through */
    }
  }
  return normalizePath(entry.path);
}

export function loadUrls() {
  const raw = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
  let urls = (raw.urls || []).filter(isContentParityUrl);
  const sample = process.env.PARITY_SAMPLE !== '0';
  const limit = parseInt(process.env.PARITY_LIMIT || '0', 10);

  if (sample) {
    const byTemplate = {};
    for (const u of urls) {
      const t = u.template || 'other';
      (byTemplate[t] ||= []).push(u);
    }
    const picked = [];
    const mustPaths = new Set(['/homepage/', '/home', '/issues', '/stories', '/in-brief']);
    for (const u of urls) {
      if (mustPaths.has(u.path) || mustPaths.has(u.path + '/')) picked.push(u);
    }
    for (const [tpl, list] of Object.entries(byTemplate)) {
      const sorted =
        tpl === 'article'
          ? [...list].sort((a, b) => (b.drupal_alias ? 1 : 0) - (a.drupal_alias ? 1 : 0))
          : list;
      const n = tpl === 'article' ? 8 : tpl === 'in_brief' ? 5 : tpl === 'issue' ? 14 : 3;
      picked.push(...sorted.slice(0, n));
    }
    const seen = new Set();
    urls = picked.filter((u) => {
      if (seen.has(u.path)) return false;
      seen.add(u.path);
      return true;
    });
  }

  urls = urls.filter(
    (u) => u.drupal_alias || !/^\/\d{4}\//.test(u.path) || u.template === 'issue',
  );

  if (limit > 0) urls = urls.slice(0, limit);
  return urls;
}

export function caseSlug(entryPath) {
  return entryPath.replace(/^\//, '').replace(/[/?#]/g, '_') || 'root';
}

export const BREAKPOINTS = [
  { label: 'desktop', width: 1440, height: 900 },
  { label: 'tablet', width: 768, height: 1024 },
  { label: 'mobile', width: 375, height: 812 },
];
