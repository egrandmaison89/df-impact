#!/usr/bin/env node
/**
 * Freeze live Jetpack Related Posts ("More stories:") for every migrated article and In Brief.
 *
 * Reads the public `jetpack-related-posts` REST field in bulk through a real browser context
 * (plain HTTP clients get the SiteGround captcha), then spot-checks it against the rendered
 * nav.jp-relatedposts-i2 on VERIFY pages (default: the QA-020 spec samples).
 *
 * Usage:
 *   node parity/scripts/fetch-wp-jetpack-related.mjs
 *   VERIFY_RANDOM=20 node parity/scripts/fetch-wp-jetpack-related.mjs
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, '../..');
const dataDir = path.join(repoRoot, 'drupal/web/modules/custom/df_migrate/data');
const outPath = path.join(dataDir, 'wp_jetpack_related_posts.json');
const WP_BASE = (process.env.WP_BASE_URL || 'https://danafarberimpact.org').replace(/\/$/, '');
const VERIFY_RANDOM = Number(process.env.VERIFY_RANDOM || 0);
const VERIFY_PATHS = [
  '/2023/01/edward-p-evans-foundation-grants-propel-mds-research/',
  '/2024/06/the-gray-foundation-empowers-team-science-against-brca-mutated-cancers/',
  '/2026/09/a-message-from-melany-duval-13/',
  '/2023/11/dismantling-gender-disparity-through-mentorship/',
  '/2026/09/more-than-a-building-name/',
  '/2024/03/lindsley-named-edward-p-evans-investigator/',
  '/2024/06/st-baldricks-foundation-2/',
];

const sources = [];
for (const [file, bundle] of [['articles.json', 'article'], ['in_brief.json', 'in_brief']]) {
  for (const row of JSON.parse(fs.readFileSync(path.join(dataDir, file), 'utf8'))) {
    if (row.wp_id) sources.push({ wp_id: Number(row.wp_id), slug: row.slug, bundle });
  }
}
const slugToWpId = new Map(sources.map((s) => [s.slug, s.wp_id]));

const decode = (s) =>
  String(s ?? '')
    .replace(/&amp;/g, '&')
    .replace(/&#0?38;/g, '&')
    .replace(/&#8217;/g, '’')
    .replace(/&#8216;/g, '‘')
    .replace(/&#8220;/g, '“')
    .replace(/&#8221;/g, '”')
    .replace(/&#8211;/g, '–')
    .replace(/&#8212;/g, '—')
    .replace(/&quot;/g, '"')
    .replace(/&#039;/g, "'");
const slugOf = (url) => decodeURIComponent(String(url).replace(/[?#].*$/, '').replace(/\/$/, '').split('/').pop() || '');

const browser = await chromium.launch();
const context = await browser.newContext({
  userAgent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36',
});
const page = await context.newPage();

async function gotoLive(url) {
  for (let attempt = 1; attempt <= 4; attempt++) {
    const resp = await page.goto(url, { waitUntil: 'load', timeout: 60_000 }).catch(() => null);
    if (resp && resp.status() === 200 && !/sgcaptcha/.test(page.url())) return resp;
    await page.waitForTimeout(2000 * attempt);
  }
  throw new Error(`live WP unreachable: ${url}`);
}

async function restPage(n) {
  const url = `${WP_BASE}/wp-json/wp/v2/posts?per_page=100&page=${n}&_fields=id,slug,link,jetpack-related-posts`;
  for (let attempt = 1; attempt <= 4; attempt++) {
    const res = await page.evaluate(async (u) => {
      const r = await fetch(u, { credentials: 'omit' });
      return { status: r.status, total: r.headers.get('x-wp-totalpages'), body: await r.text() };
    }, url);
    if (res.status === 200 && res.body.startsWith('[')) return { rows: JSON.parse(res.body), totalPages: Number(res.total || 1) };
    if (res.status === 400) return { rows: [], totalPages: 0 };
    await page.waitForTimeout(2000 * attempt);
    await gotoLive(`${WP_BASE}/`);
  }
  throw new Error(`REST page ${n} failed`);
}

await gotoLive(`${WP_BASE}/`);
const live = new Map();
for (let n = 1, total = 1; n <= total; n++) {
  const { rows, totalPages } = await restPage(n);
  total = totalPages;
  for (const row of rows) live.set(Number(row.id), row);
  process.stderr.write(`REST page ${n}/${total}: ${rows.length} posts\n`);
}

const byWpId = {};
const missing = [];
for (const src of sources) {
  const row = live.get(src.wp_id);
  const items = Array.isArray(row?.['jetpack-related-posts']) ? row['jetpack-related-posts'] : null;
  if (!items || items.length === 0) {
    missing.push({ wp_id: src.wp_id, slug: src.slug, bundle: src.bundle, reason: row ? 'no_related_items' : 'not_in_live_rest' });
    continue;
  }
  byWpId[String(src.wp_id)] = {
    wp_id: src.wp_id,
    slug: src.slug,
    bundle: src.bundle,
    wp_url: row.link,
    source: 'live-jetpack-rest',
    related: items.slice(0, 3).map((item) => ({
      url: item.url,
      slug: slugOf(item.url),
      title: decode(item.title),
      wp_id: Number(item.id) || slugToWpId.get(slugOf(item.url)) || null,
      image_src: item.img?.src ? decode(item.img.src) : null,
    })),
  };
}

const verifyPaths = [...VERIFY_PATHS];
const pool = Object.values(byWpId);
for (let i = 0; i < VERIFY_RANDOM && pool.length; i++) {
  verifyPaths.push(new URL(pool[Math.floor(Math.random() * pool.length)].wp_url).pathname);
}
const verify = [];
for (const p of verifyPaths) {
  await gotoLive(`${WP_BASE}${p}`);
  const rendered = await page.evaluate(() =>
    [...document.querySelectorAll('nav.jp-relatedposts-i2 a.jp-related-posts-i2__post-link')].map((a) => a.getAttribute('href')),
  );
  const entry = Object.values(byWpId).find((e) => new URL(e.wp_url).pathname === p);
  const frozen = entry ? entry.related.map((r) => r.url) : [];
  verify.push({ path: p, match: JSON.stringify(rendered) === JSON.stringify(frozen), rendered, frozen });
}
await browser.close();

const out = {
  generated_at: new Date().toISOString(),
  source: `${WP_BASE}/wp-json/wp/v2/posts (jetpack-related-posts field)`,
  article_count: sources.filter((s) => s.bundle === 'article').length,
  in_brief_count: sources.filter((s) => s.bundle === 'in_brief').length,
  with_related: Object.keys(byWpId).length,
  missing,
  verify: verify.map(({ path: p, match }) => ({ path: p, match })),
  by_wp_id: byWpId,
};
fs.writeFileSync(outPath, `${JSON.stringify(out, null, 1)}\n`);
console.log(JSON.stringify({ with_related: out.with_related, missing: missing.length, verify }, null, 1));
