#!/usr/bin/env node
/** Top horizontal diff bands for a wp/drupal viewport pair. */
import fs from 'fs';
import path from 'path';
import { PNG } from 'pngjs';
import pixelmatch from 'pixelmatch';

const dir = process.argv[2];
if (!dir) {
  console.error('Usage: node analyze-diff-bands.mjs <progress-subdir>');
  process.exit(1);
}

const wpPath = path.join(dir, 'wp-viewport-mac.png');
const drPath = path.join(dir, 'drupal-viewport.png');
const wp = PNG.sync.read(fs.readFileSync(wpPath));
const dr = PNG.sync.read(fs.readFileSync(drPath));
const w = Math.min(wp.width, dr.width);
const h = Math.min(wp.height, dr.height);
const diff = new PNG({ width: w, height: h });
const n = pixelmatch(wp.data, dr.data, diff.data, w, h, { threshold: 0.1 });
const rows = 40;
const rh = Math.floor(h / rows);
const bands = [];
for (let r = 0; r < rows; r++) {
  let cnt = 0;
  let tot = 0;
  const y0 = r * rh;
  const y1 = Math.min((r + 1) * rh, h);
  for (let y = y0; y < y1; y++) {
    for (let x = 0; x < w; x++) {
      const i = (w * y + x) * 4;
      tot++;
      if (diff.data[i] || diff.data[i + 1] || diff.data[i + 2]) cnt++;
    }
  }
  bands.push({ y0, y1, ratio: cnt / tot });
}
bands.sort((a, b) => b.ratio - a.ratio);
console.log(
  JSON.stringify(
    {
      dir,
      pixelPercent: +((n / (w * h)) * 100).toFixed(2),
      topBands: bands.slice(0, 5).map((b) => ({
        y: `${b.y0}-${b.y1}`,
        diffPercent: +(b.ratio * 100).toFixed(1),
      })),
    },
    null,
    2,
  ),
);
