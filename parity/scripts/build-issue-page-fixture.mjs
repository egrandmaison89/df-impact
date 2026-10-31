#!/usr/bin/env node
/**
 * Builds tracked issue-page WP fixture from parity/cache/wp-issue-pages HTML.
 * Output: drupal/web/modules/custom/df_migrate/data/issue_page_wp_fixture.json
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, '../..');
const cacheDir = path.join(repoRoot, 'parity/cache/wp-issue-pages');
const outFile = path.join(
  repoRoot,
  'drupal/web/modules/custom/df_migrate/data/issue_page_wp_fixture.json',
);

function parseIssueHtml(html, slug) {
  const entry = {
    path_alias: `/${slug}/`,
    banner_url: '',
    cover_url: '',
    main_grid_wp_post_ids: [],
    promo_wp_post_ids: [],
    wp_archive_error: /arch_err|"post":"0"/.test(html) && !/ultp-post-grid-block/.test(html),
  };
  const og = html.match(/property="og:image"\s+content="([^"]+)"/i);
  if (og) {
    entry.banner_url = og[1].replace(/&amp;/g, '&');
  }
  if (!entry.banner_url) {
    const cover = html.match(/wp-block-cover__image-background[^>]+src="([^"]+)"/i);
    if (cover) entry.banner_url = cover[1].replace(/&amp;/g, '&');
  }
  const parts = html.split('ultp-post-grid-block');
  if (parts.length >= 2) {
    const main = parts[1];
    entry.main_grid_wp_post_ids = [
      ...new Set([...main.matchAll(/post-id-(\d+)/g)].map((m) => parseInt(m[1], 10))),
    ];
  }
  if (parts.length >= 3) {
    const promo = parts[2];
    entry.promo_wp_post_ids = [
      ...new Set([...promo.matchAll(/post-id-(\d+)/g)].map((m) => parseInt(m[1], 10))),
    ].slice(0, 2);
  }
  return entry;
}

function parseBrowseCovers(html) {
  const map = {};
  const parts = html.split('issue-cover');
  for (let i = 1; i < parts.length; i++) {
    const chunk = parts[i].slice(0, 2500);
    const href = chunk.match(/href="https:\/\/danafarberimpact\.org\/([a-z0-9-]+-issue)\//i);
    const src = chunk.match(/src="(https:\/\/[^"]*\/wp-content\/uploads\/[^"]+)"/i);
    const h4 = chunk.match(/<h4[^>]*>([^<]+)/i);
    if (!href || !src || !h4) continue;
    map[`/${href[1]}/`] = {
      cover_url: src[1].replace(/&amp;/g, '&'),
      label: h4[1].trim(),
    };
  }
  return map;
}

const browseHtmlPaths = [
  path.join(repoRoot, 'parity/cache/wp-issue-pages/browse-issues.html'),
  path.join(repoRoot, 'parity/reference-pack/browse-issues/desktop/rendered.html'),
];
let browseCovers = {};
for (const p of browseHtmlPaths) {
  if (fs.existsSync(p)) {
    browseCovers = { ...browseCovers, ...parseBrowseCovers(fs.readFileSync(p, 'utf8')) };
  }
}

const issues = [];
if (fs.existsSync(cacheDir)) {
  for (const file of fs.readdirSync(cacheDir).filter((f) => f.endsWith('.html'))) {
    const slug = file.replace(/\.html$/, '');
    if (slug === 'browse-issues') continue;
    const html = fs.readFileSync(path.join(cacheDir, file), 'utf8');
    const row = parseIssueHtml(html, slug);
    const cover = browseCovers[row.path_alias];
    if (cover?.cover_url) {
      row.cover_url = cover.cover_url;
    }
    issues.push(row);
  }
}
issues.sort((a, b) => a.path_alias.localeCompare(b.path_alias));

const payload = {
  generated_from: 'parity/cache/wp-issue-pages + browse-issues covers',
  generated_at: new Date().toISOString(),
  issues,
};
fs.mkdirSync(path.dirname(outFile), { recursive: true });
fs.writeFileSync(outFile, JSON.stringify(payload, null, 2) + '\n');
console.log(JSON.stringify({ outFile, count: issues.length }, null, 2));
