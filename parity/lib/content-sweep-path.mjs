import fs from 'node:fs';
import path from 'node:path';

/** WP paths whose reference-pack folder differs from pathToPackSlug. */
export const REF_PACK_SLUG_OVERRIDES = {
  '/home': 'homepage',
  '/tag/digital-exclusives': 'category_digital-exclusives',
  '/stories': 'issues',
};

/** Strip pagination suffix so page 2+ can reuse page-1 ref-pack HTML. */
export function baselinePathForFrozen(publicPath) {
  const normalized = normalizePath(publicPath);
  if (/^\/(?:category|tag)\//.test(normalized)) {
    return normalized;
  }
  return normalized.replace(/\/page\/\d+$/i, '');
}

export function normalizePath(p) {
  const x = p.startsWith('/') ? p : `/${p}`;
  const trimmed = x.replace(/\/+$/, '');
  return trimmed || '/';
}

export function pathToPackSlug(p) {
  const normalized = normalizePath(p);
  if (REF_PACK_SLUG_OVERRIDES[normalized]) {
    return REF_PACK_SLUG_OVERRIDES[normalized];
  }
  if (normalized === '/') return 'root';
  if (normalized === '/homepage') return 'homepage';
  const m = normalized.match(/^\/(\d{4})\/(\d{2})\/(.+?)$/);
  if (m) return `${m[1]}_${m[2]}_${m[3]}`;
  return normalized.replace(/^\//, '').replace(/\//g, '_');
}

export function frozenWpHtmlPath(packRoot, publicPath) {
  const normalized = normalizePath(publicPath);
  const overrideSlug = REF_PACK_SLUG_OVERRIDES[normalized];
  if (overrideSlug) {
    const overrideFile = path.join(packRoot, overrideSlug, 'desktop', 'rendered.html');
    if (fs.existsSync(overrideFile)) {
      return overrideFile;
    }
  }
  const basePath = baselinePathForFrozen(publicPath);
  const slug = pathToPackSlug(basePath);
  const file = path.join(packRoot, slug, 'desktop', 'rendered.html');
  return fs.existsSync(file) ? file : null;
}
