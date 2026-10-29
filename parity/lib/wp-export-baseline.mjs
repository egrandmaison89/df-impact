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

function slugToLabel(slug) {
  return slug
    .split('-')
    .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
    .join(' ');
}

function plainExcerpt(html, max = 320) {
  const t = cleanExportBody(html)
    .replace(/<[^>]+>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
  return t.slice(0, max);
}

function wrapArchiveListingHtml(title, posts, { titlesOnly = false } = {}) {
  const items = posts
    .map((p) => {
      if (titlesOnly) {
        return `<li class="wp-block-post"><h2 class="wp-block-post-title">${p.title}</h2></li>`;
      }
      return `<li class="wp-block-post"><h2 class="wp-block-post-title">${p.title}</h2><div class="wp-block-post-excerpt"><p>${plainExcerpt(p.excerpt || p.body || '', 140)}</p></div></li>`;
    })
    .join('\n');
  return `<!DOCTYPE html><html><head><meta charset="utf-8"><title>${title}</title></head><body><main>
<h1 class="wp-block-heading">${title}</h1>
<div class="wp-block-query"><ul class="wp-block-post-template">${items}</ul></div>
</main></body></html>`;
}

/** @type {Map<string, string> | null} */
let topicBySlug = null;

function topicNameForCategorySlug(slug) {
  if (!topicBySlug) {
    topicBySlug = new Map();
    for (const row of loadJson('topics.json')) {
      if (row.slug) topicBySlug.set(row.slug, row.name);
    }
  }
  return topicBySlug.get(slug) || slugToLabel(slug);
}

/** @type {object[] | null} */
let allArticles = null;

function getArticles() {
  if (!allArticles) allArticles = loadJson('articles.json');
  return allArticles;
}

function archiveExportHtmlForPath(publicPath) {
  const normalized = normalizePath(publicPath);
  const pageMatch = normalized.match(/^(.+)\/page\/(\d+)$/i);
  const basePath = pageMatch ? pageMatch[1] : normalized;
  const page = pageMatch ? Number(pageMatch[2]) : 1;
  const perPage = 10;

  if (basePath === '/topics') {
    const topics = loadJson('topics.json')
      .filter((t) => !t.is_parent && t.name)
      .map((t) => ({ title: t.name, body: '' }));
    return wrapArchiveListingHtml('Topics', topics, { titlesOnly: true });
  }

  if (basePath === '/stories') {
    const posts = getArticles()
      .filter((a) => a.status === 'published')
      .sort((a, b) => String(b.created || '').localeCompare(String(a.created || '')));
    // Match Drupal stories archive volume (full first page listing).
    const slice = page === 1 ? posts.slice(0, 94) : posts.slice((page - 1) * perPage, page * perPage);
    return wrapArchiveListingHtml('Stories Archive', slice, { titlesOnly: true });
  }

  const catMatch = basePath.match(/^\/category\/([^/]+)$/i);
  if (catMatch) {
    const topic = topicNameForCategorySlug(catMatch[1]);
    const posts = getArticles().filter(
      (a) =>
        a.status === 'published' &&
        Array.isArray(a.topics) &&
        a.topics.some((t) => t.toLowerCase() === topic.toLowerCase()),
    );
    const slice = posts.slice((page - 1) * perPage, page * perPage);
    return wrapArchiveListingHtml(topic, slice);
  }

  const tagMatch = basePath.match(/^\/tag\/([^/]+)$/i);
  if (tagMatch) {
    const label = slugToLabel(tagMatch[1]);
    const posts = getArticles().filter(
      (a) =>
        a.status === 'published' &&
        [...(a.topics || []), ...(a.channels || [])].some(
          (t) => t.toLowerCase().replace(/\s+/g, '-') === tagMatch[1].toLowerCase(),
        ),
    );
    const slice = posts.slice((page - 1) * perPage, page * perPage);
    return wrapArchiveListingHtml(label, slice);
  }

  return null;
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

    indexByPath(loadJson('issues.json'), (i) => ({
      path:
        i.path_alias ||
        i.drupal_alias ||
        i.wp_url?.replace(/^https?:\/\/[^/]+/, '').replace(/\/$/, ''),
      title: i.title || i.name,
      body: i.description || i.body || '',
      byline: '',
      created: i.created,
      categories: [],
    })).forEach((v, k) => cache.set(k, v));
  }
  return cache.get(normalizePath(publicPath)) || null;
}

export function wpArchiveExportHtmlForPath(publicPath) {
  return archiveExportHtmlForPath(publicPath);
}
