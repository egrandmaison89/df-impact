#!/usr/bin/env node
/** Build archive_page_wp_order.json from frozen reference-pack category/tag HTML. */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import * as cheerio from 'cheerio';
import { frozenWpHtmlPath, normalizePath, pathToPackSlug } from '../lib/content-sweep-path.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const packRoot = path.join(parityRoot, 'reference-pack');
const manifest = JSON.parse(
  fs.readFileSync(path.join(parityRoot, 'manifest/site-manifest.json'), 'utf8'),
);
const outFile = path.join(
  parityRoot,
  '../drupal/web/modules/custom/df_migrate/data/archive_page_wp_order.json',
);

function pathsFromHtml(html) {
  const $ = cheerio.load(html);
  const root = $('main .wp-block-query, .wp-block-query').first();
  const scope = root.length ? root : $('main');
  const paths = [];
  scope.find('.wp-block-post-title a[href], h2 a[href], .wp-block-post a[href]').each((_, el) => {
    const href = $(el).attr('href') || '';
    try {
      const u = new URL(href, 'https://danafarberimpact.org');
      if (!u.pathname || u.pathname === '/') return;
      if (!/danafarberimpact\.org/i.test(u.hostname)) return;
      const p = normalizePath(u.pathname);
      if (!paths.includes(p)) paths.push(p);
    } catch {
      /* skip */
    }
  });
  return paths;
}

const archives = {};
for (const entry of manifest.urls || []) {
  if (entry.template !== 'archive') continue;
  const p = entry.path;
  if (!p || /\/feed\/?$/i.test(p)) continue;
  const file = frozenWpHtmlPath(packRoot, p);
  if (!file) continue;
  const html = fs.readFileSync(file, 'utf8');
  const articlePaths = pathsFromHtml(html);
  if (articlePaths.length) {
    archives[normalizePath(p)] = articlePaths;
  }
}

fs.mkdirSync(path.dirname(outFile), { recursive: true });
fs.writeFileSync(
  outFile,
  JSON.stringify({ generatedAt: new Date().toISOString(), archives }, null, 2),
);
console.log('wrote', outFile, Object.keys(archives).length, 'archives');
