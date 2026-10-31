#!/usr/bin/env node
/** Emit df_impact wp_port library snippet from reference-pack assets-index.json */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const index = JSON.parse(fs.readFileSync(path.join(root, 'reference-pack/assets-index.json'), 'utf8'));

const prefer = [
  'themes_blockmag_style.css',
  'themes_blockmagchild',
  'uploads_maxmegamenu_style.css',
  'plugins_ultimate-post_assets_css_style.min.css',
  'plugins_stackable-ultimate-gutenberg-blocks_dist_frontend_blocks.css',
  'plugins_stackable-ultimate-gutenberg-blocks_dist_frontend_blocks_responsive.css',
  'plugins_add-search-to-menu_public_css_ivory-search.min.css',
  'uploads_font-awesome',
  'fonts.googleapis.com_css',
  'use.fontawesome.com_releases_v6.2.1_css_all.css',
];

const files = Object.values(index).map((e) => e.file.replace(/^assets\//, ''));
const css = [];
for (const needle of prefer) {
  const hit = files.find((f) => f.includes(needle));
  if (hit && !css.includes(hit)) css.push(hit);
}
for (const f of files) {
  if (f.endsWith('.css') && !css.includes(f)) css.push(f);
}

const lines = css.map((f) => `      wp-assets/${f}: { weight: ${css.indexOf(f)} }`).join('\n');
console.log(`wp_port:
  version: reference-pack
  css:
    theme:
${lines}
  js:
    wp-assets/cdnjs.cloudflare.com_ajax_libs_jquery_3.3.1_jquery.min.js: {}
  dependencies:
    - core/jquery
`);
