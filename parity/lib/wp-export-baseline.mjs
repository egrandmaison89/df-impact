import fs from 'node:fs';
import path from 'node:path';
import { repoRoot } from './manifest-urls.mjs';
import { normalizePath } from './content-sweep-path.mjs';

function cleanExportBody(body) {
  return (body || '')
    .replace(/<!--[\s\S]*?-->/g, '')
    .replace(/\[\/?(?:caption|embed|gallery)[^\]]*\]/gi, '');
}

function wrapExportHtml({ title, body, byline, created, categories = [] }) {
  const cleanedBody = cleanExportBody(body);
  const catLinks = categories.map((c) => `<a rel="category tag">${c}</a>`).join('');
  return `<!DOCTYPE html><html><head><meta charset="utf-8"><title>${title}</title>
<meta property="article:published_time" content="${created || ''}">
</head><body><main><article class="post">
<h1 class="entry-title">${title}</h1>
<div class="byline">${byline || ''}</div>
<div class="wp-block-post-terms">${catLinks}</div>
<div class="entry-content wp-block-post-content">${cleanedBody}</div>
</article></main></body></html>`;
}

function loadJson(rel) {
  const file = path.join(repoRoot, 'drupal/web/modules/custom/df_migrate/data', rel);
  if (!fs.existsSync(file)) return [];
  return JSON.parse(fs.readFileSync(file, 'utf8'));
}

function indexByPath(rows, pick) {
  const map = new Map();
  for (const row of rows) {
    const meta = pick(row);
    if (!meta?.path) continue;
    map.set(normalizePath(meta.path), wrapExportHtml(meta));
  }
  return map;
}

/** @type {Map<string, string> | null} */
let cache = null;

export function wpExportHtmlForPath(publicPath) {
  if (!cache) {
    cache = new Map();
    const articles = loadJson('articles.json');
    indexByPath(articles, (a) => ({
      path: a.drupal_alias || new URL(a.wp_url).pathname,
      title: a.title,
      body: a.body,
      byline: a.byline,
      created: a.created,
      categories: [...(a.topics || []), ...(a.channels || [])],
    })).forEach((v, k) => cache.set(k, v));

    const briefs = loadJson('in_brief.json');
    indexByPath(briefs, (b) => ({
      path: b.drupal_alias || b.wp_url?.replace(/^https?:\/\/[^/]+/, ''),
      title: b.title,
      body: b.body || b.field_summary,
      byline: b.byline,
      created: b.created,
      categories: b.topics || [],
    })).forEach((v, k) => cache.set(k, v));

    const issues = loadJson('issues.json');
    indexByPath(issues, (i) => ({
      path: i.drupal_alias || i.wp_url?.replace(/^https?:\/\/[^/]+/, ''),
      title: i.title,
      body: i.description || i.body || '',
      byline: '',
      created: i.created,
      categories: [],
    })).forEach((v, k) => cache.set(k, v));
  }
  return cache.get(normalizePath(publicPath)) || null;
}
