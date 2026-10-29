#!/usr/bin/env node
/**
 * Build wp_jetpack_related_posts.json from WordPress REST export
 * (drupal/migration-data/posts_page_*_raw.json jetpack-related-posts field).
 *
 * Usage: node parity/scripts/build-wp-jetpack-related-from-export.mjs
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, '../..');
const articlesPath = path.join(
  repoRoot,
  'drupal/web/modules/custom/df_migrate/data/articles.json',
);
const migrationDir = path.join(repoRoot, 'drupal/migration-data');
const outPath = path.join(
  repoRoot,
  'drupal/web/modules/custom/df_migrate/data/wp_jetpack_related_posts.json',
);

const articles = JSON.parse(fs.readFileSync(articlesPath, 'utf8'));
const inBriefPath = path.join(
  repoRoot,
  'drupal/web/modules/custom/df_migrate/data/in_brief.json',
);
const slugToWpId = new Map();
for (const row of articles) {
  if (row.slug) slugToWpId.set(row.slug, row.wp_id);
}
if (fs.existsSync(inBriefPath)) {
  for (const row of JSON.parse(fs.readFileSync(inBriefPath, 'utf8'))) {
    if (row.slug) slugToWpId.set(row.slug, row.wp_id);
  }
}

const jetpackByWpId = new Map();
for (const file of fs.readdirSync(migrationDir)) {
  if (!file.startsWith('posts_page_') || !file.endsWith('_raw.json')) continue;
  const posts = JSON.parse(fs.readFileSync(path.join(migrationDir, file), 'utf8'));
  for (const post of posts) {
    if (post.type !== 'post' || !post.id) continue;
    jetpackByWpId.set(post.id, post['jetpack-related-posts'] || []);
  }
}

function slugFromUrl(url) {
  try {
    return decodeURIComponent(String(url).replace(/\/$/, '').split('/').pop() || '');
  } catch {
    return String(url).replace(/\/$/, '').split('/').pop() || '';
  }
}

const byWpId = {};
let withRelated = 0;

for (const row of articles) {
  const wpId = row.wp_id;
  const raw = jetpackByWpId.get(wpId) || [];
  const related = raw.slice(0, 3).map((item) => {
    const slug = slugFromUrl(item.url || '');
    return {
      url: item.url || '',
      slug,
      title: item.title || '',
      wp_id: item.id ?? slugToWpId.get(slug) ?? null,
      image_src: item.img?.src || item.featured_image || null,
    };
  });
  if (related.length) withRelated += 1;
  byWpId[String(wpId)] = {
    wp_id: wpId,
    slug: row.slug,
    wp_url: row.wp_url || '',
    source: 'migration-export',
    parse: related.length ? 'ok' : 'empty',
    related,
  };
}

const out = {
  generated_at: new Date().toISOString(),
  source: 'drupal/migration-data/posts_page_*_raw.json',
  article_count: articles.length,
  with_related: withRelated,
  by_wp_id: byWpId,
};

fs.writeFileSync(outPath, JSON.stringify(out, null, 2));
console.log(
  JSON.stringify(
    { outPath, article_count: articles.length, with_related: withRelated },
    null,
    2,
  ),
);
