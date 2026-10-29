/**
 * Offline: reference-pack WP head fields parse and self-match (metaMatch baseline).
 */
import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { pickMetaFromHtml } from '../lib/head-meta.mjs';
import { metaMatches } from '../lib/thresholds.mjs';
import { resolveParityRoot } from '../lib/parity-root.mjs';

const parityRoot = resolveParityRoot();
const packRoot = path.join(parityRoot, 'reference-pack');

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
  test(`ref-pack head meta ${slug}`, () => {
    const html = fs.readFileSync(path.join(packRoot, slug, 'desktop', 'rendered.html'), 'utf8');
    const meta = pickMetaFromHtml(html);
    expect(meta.title.length, 'title').toBeGreaterThan(0);
    expect(meta.title, 'title includes Impact Magazine').toContain('Impact Magazine');
    expect(metaMatches(meta, meta), 'metaMatches self').toBe(true);
    if (meta.ogTitle) {
      expect(meta.ogTitle, 'og:title aligns with document title').toBe(meta.title);
    }
  });
}
