/**
 * Expected Yoast-style head fields derived from committed migration JSON + ref-pack + overrides.
 *
 * Method (no frozen baseline required):
 * 1. articles.json / in_brief.json — title, meta_description, wp_url (canonical), featured image URL
 * 2. df_metatag/data/wp_head_overrides.json — production canonicals, external defycancer URLs, JSON-LD
 * 3. reference-pack desktop rendered.html — archive/landing paths not in JSON exports
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { pickMetaFromHtml } from './head-meta.mjs';
import { normalizeMetaValue } from './thresholds.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const repoRoot = path.resolve(parityRoot, '..');

const IMPACT_SUFFIX = ' - Impact Magazine';

function normPath(p) {
  const x = (p || '').trim();
  if (!x || x === '/') return '/';
  const withSlash = x.startsWith('/') ? x : `/${x}`;
  return withSlash.replace(/\/+$/, '') || '/';
}

function uploadsImageFromUrl(url) {
  if (!url || typeof url !== 'string') return '';
  const decoded = url.replace(/&amp;/g, '&');
  const m = decoded.match(/https:\/\/danafarberimpact\.org\/wp-content\/uploads\/[^"'\s?)]+/i);
  return m ? m[0] : '';
}

function firstUploadInBody(html) {
  if (!html) return '';
  const m = html.match(/https:\/\/danafarberimpact\.org\/wp-content\/uploads\/[^"'\s>]+\.(?:jpe?g|png|webp|gif)/i);
  return m ? m[0] : '';
}

function buildFromStory(row) {
  const title = normalizeMetaValue(`${row.title}${IMPACT_SUFFIX}`);
  const description = normalizeMetaValue(
    (row.meta_description && String(row.meta_description).trim()) || row.excerpt || '',
  );
  let canonical = (row.wp_url || '').trim();
  if (canonical && !canonical.endsWith('/')) canonical += '/';
  const ogImage = uploadsImageFromUrl(row.featured_image_url);
  return {
    title,
    description,
    ogTitle: title,
    ogDescription: description,
    ogImage,
    canonical,
    jsonLd: '',
  };
}

function loadJson(rel) {
  const full = path.join(repoRoot, rel);
  if (!fs.existsSync(full)) return null;
  return JSON.parse(fs.readFileSync(full, 'utf8'));
}

function loadOverrides() {
  const p = path.join(
    repoRoot,
    'drupal/web/modules/custom/df_metatag/data/wp_head_overrides.json',
  );
  if (!fs.existsSync(p)) return {};
  return JSON.parse(fs.readFileSync(p, 'utf8'));
}

function loadRefPackMeta() {
  const packRoot = path.join(parityRoot, 'reference-pack');
  const out = new Map();
  if (!fs.existsSync(packRoot)) return out;
  for (const ent of fs.readdirSync(packRoot, { withFileTypes: true })) {
    if (!ent.isDirectory() || ent.name.startsWith('phase') || ent.name === 'assets') continue;
    const htmlPath = path.join(packRoot, ent.name, 'desktop', 'rendered.html');
    if (!fs.existsSync(htmlPath)) continue;
    const html = fs.readFileSync(htmlPath, 'utf8');
    const meta = pickMetaFromHtml(html);
    const pagePath = packSlugToPath(ent.name);
    out.set(normPath(pagePath), meta);
  }
  return out;
}

function packSlugToPath(name) {
  if (name === 'root') return '/';
  if (name === 'homepage') return '/homepage';
  if (name.startsWith('category_')) return `/category/${name.slice('category_'.length).replace(/_/g, '-')}`;
  const dateMatch = name.match(/^(\d{4})_(\d{2})_(.+)$/);
  if (dateMatch) return `/${dateMatch[1]}/${dateMatch[2]}/${dateMatch[3].replace(/_/g, '-')}`;
  return `/${name.replace(/_/g, '-')}`;
}

function applyOverrides(meta, override) {
  if (!override || typeof override !== 'object') return meta;
  const next = { ...meta };
  if (override.title) next.title = normalizeMetaValue(override.title);
  if (override.description !== undefined) next.description = normalizeMetaValue(override.description);
  if (override.og_title) next.ogTitle = normalizeMetaValue(override.og_title);
  if (override.og_description !== undefined) {
    next.ogDescription = normalizeMetaValue(override.og_description);
  }
  if (override.og_image) next.ogImage = override.og_image;
  if (override.canonical) next.canonical = override.canonical;
  if (override.json_ld) next.jsonLd = override.json_ld;
  return next;
}

/** @returns {Map<string, import('./thresholds.mjs').*>} */
export function buildExpectedMetaIndex() {
  /** @type {Map<string, object>} */
  const index = new Map();

  const articles = loadJson('drupal/web/modules/custom/df_migrate/data/articles.json');
  if (Array.isArray(articles)) {
    for (const row of articles) {
      if (row.status !== 'published') continue;
      index.set(normPath(row.drupal_alias), buildFromStory(row));
    }
  }

  const briefs = loadJson('drupal/web/modules/custom/df_migrate/data/in_brief.json');
  if (Array.isArray(briefs)) {
    for (const row of briefs) {
      if (row.status !== 'published') continue;
      index.set(normPath(row.drupal_alias), buildFromStory(row));
    }
  }

  for (const [p, meta] of loadRefPackMeta()) {
    if (!index.has(p)) index.set(p, meta);
  }

  const overrides = loadOverrides();
  for (const [rawPath, ovr] of Object.entries(overrides)) {
    const p = normPath(rawPath);
    const base = index.get(p) || {
      title: '',
      description: '',
      ogTitle: '',
      ogDescription: '',
      ogImage: '',
      canonical: '',
      jsonLd: '',
    };
    index.set(p, applyOverrides(base, ovr));
  }

  return index;
}

const PATH_ALIASES = {
  '/about': '/about-impact',
  '/home': '/homepage',
};

export function expectedMetaForPath(index, publicPath) {
  const p = normPath(publicPath);
  const keys = PATH_ALIASES[p] ? [PATH_ALIASES[p], p] : [p];
  for (const key of keys) {
    if (index.has(key)) return index.get(key);
    if (key !== '/' && index.has(`${key}/`)) return index.get(`${key}/`);
  }
  return null;
}
