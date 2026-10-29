#!/usr/bin/env node
/**
 * Scrape Jetpack "More stories" from live WordPress HTML (optional refresh).
 * Prefer migration export when live WP is blocked:
 *   node parity/scripts/build-wp-jetpack-related-from-export.mjs
 *
 * Usage:
 *   node parity/scripts/fetch-wp-jetpack-related.mjs
 *   LIMIT=20 node parity/scripts/fetch-wp-jetpack-related.mjs
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
const outPath = path.join(
  repoRoot,
  'drupal/web/modules/custom/df_migrate/data/wp_jetpack_related_posts.json',
);

const WP_BASE = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');
const LIMIT = process.env.LIMIT ? Number(process.env.LIMIT) : 0;
const CONCURRENCY = Number(process.env.FETCH_CONCURRENCY || 3);
const MAX_ATTEMPTS = Number(process.env.FETCH_MAX_ATTEMPTS || 4);
const BASE_DELAY_MS = Number(process.env.FETCH_RETRY_BASE_MS || 2000);

const articles = JSON.parse(fs.readFileSync(articlesPath, 'utf8'));
const slugToWpId = new Map();
for (const row of articles) {
  if (row.slug) slugToWpId.set(row.slug, row.wp_id);
}

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

function parseRelated(html) {
  const nav = html.match(/<nav class="jp-relatedposts-i2[\s\S]*?<\/nav>/i);
  if (!nav) {
    return { related: [], reason: 'no_nav' };
  }
  const block = nav[0];
  const related = [];
  const re = /<a[^>]*jp-related-posts-i2__post-link[^>]*>([\s\S]*?)<\/a>/gi;
  let m;
  while ((m = re.exec(block)) !== null) {
    const openEnd = block.indexOf('>', m.index);
    const openTag = block.slice(m.index, openEnd + 1);
    const hrefM = openTag.match(/href="([^"]+)"/i);
    if (!hrefM) continue;
    const url = hrefM[1].replace(/&amp;/g, '&').replace(/&#038;/g, '&');
    const inner = m[1];
    const title = inner.replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim();
    const slug = decodeURIComponent(url.replace(/\/$/, '').split('/').pop() || '');
    const img = inner.match(/class="jp-related-posts-i2__post-img"[^>]*src="([^"]+)"/i);
    related.push({
      url,
      slug,
      title,
      wp_id: slugToWpId.get(slug) ?? null,
      image_src: img ? img[1].replace(/&amp;/g, '&').replace(/&#038;/g, '&') : null,
    });
  }
  return { related: related.slice(0, 3), reason: related.length ? 'ok' : 'empty_nav' };
}

async function fetchHtml(url) {
  for (let attempt = 1; attempt <= MAX_ATTEMPTS; attempt++) {
    try {
      const resp = await fetch(url, {
        headers: { 'User-Agent': 'df-impact-jetpack-related-fetch/1.0' },
        redirect: 'follow',
      });
      const html = await resp.text();
      const captcha =
        resp.status === 202 ||
        (/sgcaptcha|captcha/i.test(html) && html.length < 35000);
      if (resp.status === 200 && !captcha && html.includes('jp-relatedposts-i2')) {
        return { html, status: resp.status, captcha: false };
      }
      if (resp.status === 200 && !captcha && html.length > 50000) {
        return { html, status: resp.status, captcha: false };
      }
      if (attempt < MAX_ATTEMPTS) {
        await sleep(BASE_DELAY_MS * 2 ** (attempt - 1));
      } else {
        return { html, status: resp.status, captcha };
      }
    } catch (e) {
      if (attempt >= MAX_ATTEMPTS) throw e;
      await sleep(BASE_DELAY_MS * 2 ** (attempt - 1));
    }
  }
  return { html: '', status: 0, captcha: true };
}

const rows = LIMIT > 0 ? articles.slice(0, LIMIT) : articles;
const byWpId = {};
let ok = 0;
let idx = 0;

async function worker() {
  while (idx < rows.length) {
    const i = idx++;
    const row = rows[i];
    const wpId = row.wp_id;
    const url = row.wp_url || `${WP_BASE}${row.drupal_alias}`;
    process.stderr.write(`[${i + 1}/${rows.length}] wp_id=${wpId} ${row.slug}\n`);
    try {
      const { html, status, captcha } = await fetchHtml(url);
      const parsed = parseRelated(html);
      byWpId[String(wpId)] = {
        wp_id: wpId,
        slug: row.slug,
        wp_url: url,
        http_status: status,
        captcha: Boolean(captcha),
        parse: parsed.reason,
        related: parsed.related,
      };
      if (parsed.related.length) ok += 1;
    } catch (e) {
      byWpId[String(wpId)] = {
        wp_id: wpId,
        slug: row.slug,
        wp_url: url,
        error: String(e),
        related: [],
      };
    }
    await sleep(400);
  }
}

await Promise.all(Array.from({ length: CONCURRENCY }, () => worker()));

const out = {
  generated_at: new Date().toISOString(),
  wp_base: WP_BASE,
  article_count: rows.length,
  with_related: ok,
  by_wp_id: byWpId,
};

fs.writeFileSync(outPath, JSON.stringify(out, null, 2));
console.log(JSON.stringify({ outPath, article_count: rows.length, with_related: ok }, null, 2));
