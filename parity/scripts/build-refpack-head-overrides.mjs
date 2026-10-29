#!/usr/bin/env node
/**
 * Merge Yoast head fields from reference-pack desktop HTML into wp_head_overrides.json.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { pickMetaFromHtml } from '../lib/head-meta.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const packRoot = path.join(parityRoot, 'reference-pack');
const overridesPath = path.join(
  parityRoot,
  '../drupal/web/modules/custom/df_metatag/data/wp_head_overrides.json',
);

function packSlugToPath(name) {
  if (name === 'root') return '/';
  if (name === 'homepage') return '/homepage';
  if (name.startsWith('category_')) return `/category/${name.slice('category_'.length).replace(/_/g, '-')}`;
  const dateMatch = name.match(/^(\d{4})_(\d{2})_(.+)$/);
  if (dateMatch) return `/${dateMatch[1]}/${dateMatch[2]}/${dateMatch[3].replace(/_/g, '-')}`;
  return `/${name.replace(/_/g, '-')}`;
}

const overrides = JSON.parse(fs.readFileSync(overridesPath, 'utf8'));

const slugs = fs
  .readdirSync(packRoot, { withFileTypes: true })
  .filter(
    (d) =>
      d.isDirectory() &&
      d.name !== 'assets' &&
      !d.name.startsWith('phase') &&
      fs.existsSync(path.join(packRoot, d.name, 'desktop', 'rendered.html')),
  )
  .map((d) => d.name);

for (const slug of slugs) {
  const pagePath = packSlugToPath(slug);
  const html = fs.readFileSync(path.join(packRoot, slug, 'desktop', 'rendered.html'), 'utf8');
  const meta = pickMetaFromHtml(html);
  const entry = {
    ...(overrides[pagePath] || {}),
    title: meta.title,
    description: meta.description,
    og_title: meta.ogTitle || meta.title,
    og_description: meta.ogDescription || meta.description,
    og_image: meta.ogImage,
    canonical: meta.canonical,
    json_ld: meta.jsonLd,
  };
  overrides[pagePath] = entry;
  if (pagePath === '/about-impact') {
    overrides['/about'] = { ...(overrides['/about'] || {}), ...entry };
  }
  if (pagePath === '/homepage') {
    overrides['/home'] = {
      ...(overrides['/home'] || {}),
      ...entry,
      canonical: meta.canonical,
    };
  }
}

fs.writeFileSync(overridesPath, JSON.stringify(overrides, null, 2) + '\n');
console.log(`Updated ${slugs.length} ref-pack paths in ${overridesPath}`);
