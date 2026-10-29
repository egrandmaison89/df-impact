#!/usr/bin/env node
/**
 * Builds archive row excerpt map from reference-pack HTML (listing text parity).
 */
import fs from 'node:fs';
import path from 'node:path';
import * as cheerio from 'cheerio';
import { fileURLToPath } from 'node:url';
import { frozenWpHtmlPath, normalizePath } from '../lib/content-sweep-path.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, '../..');
const packRoot = path.join(repoRoot, 'parity/reference-pack');
const manifest = JSON.parse(
  fs.readFileSync(path.join(repoRoot, 'parity/manifest/site-manifest.json'), 'utf8'),
);
const articles = JSON.parse(
  fs.readFileSync(
    path.join(repoRoot, 'drupal/web/modules/custom/df_migrate/data/articles.json'),
    'utf8',
  ),
);
const briefs = JSON.parse(
  fs.readFileSync(
    path.join(repoRoot, 'drupal/web/modules/custom/df_migrate/data/in_brief.json'),
    'utf8',
  ),
);

const aliasByWpPath = new Map();
for (const row of [...articles, ...briefs]) {
  const alias = row.drupal_alias || row.wp_url?.replace(/^https?:\/\/[^/]+/, '');
  const wpPath = row.wp_url?.replace(/^https?:\/\/[^/]+/, '').replace(/\/$/, '');
  if (alias && wpPath) {
    aliasByWpPath.set(normalizePath(wpPath), normalizePath(alias));
  }
}

const out = {};
for (const entry of manifest.urls || []) {
  if (entry.template !== 'archive') continue;
  const archivePath = normalizePath(entry.path);
  const frozen = frozenWpHtmlPath(packRoot, archivePath);
  if (!frozen) continue;
  const html = fs.readFileSync(frozen, 'utf8');
  const $ = cheerio.load(html);
  $('li.wp-block-post, main .wp-block-query .wp-block-post').each((_, el) => {
    const excerpt = $(el)
      .find('.wp-block-post-excerpt__excerpt')
      .first()
      .text()
      .replace(/\s+/g, ' ')
      .trim();
    if (!excerpt) return;
    let articlePath = '';
    const href =
      $(el).find('h2.wp-block-post-title a').attr('href') ||
      $(el).find('a').first().attr('href') ||
      '';
    try {
      articlePath = normalizePath(new URL(href, 'https://danafarberimpact.org').pathname);
    } catch {
      return;
    }
    const alias = aliasByWpPath.get(articlePath) || articlePath;
    out[alias] = excerpt;
  });
}

const outFile = path.join(
  repoRoot,
  'drupal/web/modules/custom/df_migrate/data/archive_row_wp_excerpts.json',
);
fs.writeFileSync(outFile, JSON.stringify({ generatedAt: new Date().toISOString(), excerpts: out }, null, 2));
console.log(JSON.stringify({ outFile, keys: Object.keys(out).length }, null, 2));
