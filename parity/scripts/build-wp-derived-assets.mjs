#!/usr/bin/env node
/**
 * Build extracted-wp-inline.css and blockmagchild-header-footer.css from reference-pack HTML.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const packRoot = path.join(root, 'reference-pack');
const dest = path.join(root, '../drupal/web/themes/custom/df_impact/wp-assets');

const CHROME_RE =
  /impact-header|desktop-header|mobile-header|mega-menu|max-mega-menu|footer-with-three|supplement|header-logo|gtranslate|\.scrolled|homepage-banner-button/i;

function extractStyles(html) {
  const blocks = [];
  const re = /<style[^>]*id="([^"]*)"[^>]*>([\s\S]*?)<\/style>/gi;
  let m;
  while ((m = re.exec(html)) !== null) {
    blocks.push({ id: m[1], css: m[2].trim() });
  }
  return blocks;
}

const slugs = fs
  .readdirSync(packRoot, { withFileTypes: true })
  .filter((d) => d.isDirectory())
  .map((d) => d.name);

const inlineAll = [];
const chrome = [];

for (const slug of slugs) {
  const htmlPath = path.join(packRoot, slug, 'desktop', 'rendered.html');
  if (!fs.existsSync(htmlPath)) continue;
  const html = fs.readFileSync(htmlPath, 'utf8');
  for (const { id, css } of extractStyles(html)) {
    if (!css) continue;
    inlineAll.push(`/* ${slug} :: ${id} */\n${css}\n`);
    if (
      CHROME_RE.test(id) ||
      CHROME_RE.test(css.slice(0, 2000)) ||
      id.includes('blockmagchild')
    ) {
      chrome.push(`/* ${slug} :: ${id} */\n${css}\n`);
    }
  }
}

fs.mkdirSync(dest, { recursive: true });
fs.writeFileSync(
  path.join(dest, 'extracted-wp-inline.css'),
  `${inlineAll.join('\n')}\n`,
);
fs.writeFileSync(
  path.join(dest, 'blockmagchild-header-footer.css'),
  chrome.length ? `${chrome.join('\n')}\n` : '/* no chrome-specific inline rules mined */\n',
);
console.log(
  `Wrote derived CSS (${inlineAll.length} inline blocks, ${chrome.length} chrome-related)`,
);
