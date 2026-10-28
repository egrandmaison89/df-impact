#!/usr/bin/env node
/**
 * Package a frozen baseline for sharing (no full-size PNGs in git).
 *
 * Produces parity/baselines/<id>-lite.zip with:
 *   - baseline-meta.json
 *   - every wordpress.json (text, meta, content — parity text/meta gates)
 *   - manifest-lite.json (paths + sizes of full PNGs on disk for optional local pixel runs)
 *
 * Full-pixel comparison still needs full PNGs on the machine that runs Playwright,
 * or use PARITY_WP_BASELINE on the owner's Mac where the full capture lives.
 */
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');

const id = process.env.PARITY_BASELINE_ID;
if (!id) {
  console.error('Set PARITY_BASELINE_ID (e.g. df-impact-2026-10-28)');
  process.exit(1);
}

const srcRoot = path.join(parityRoot, 'baselines', id);
if (!fs.existsSync(srcRoot)) {
  console.error(`Missing baseline directory: ${srcRoot}`);
  process.exit(1);
}

const staging = path.join(parityRoot, 'baselines', `.staging-${id}-lite`);
const outZip = path.join(parityRoot, 'baselines', `${id}-lite.zip`);

function walk(dir, acc = []) {
  for (const name of fs.readdirSync(dir)) {
    const p = path.join(dir, name);
    if (fs.statSync(p).isDirectory()) walk(p, acc);
    else acc.push(p);
  }
  return acc;
}

fs.rmSync(staging, { recursive: true, force: true });
fs.mkdirSync(staging, { recursive: true });

const pngIndex = [];
for (const file of walk(srcRoot)) {
  const rel = path.relative(srcRoot, file);
  if (rel === `${id}-lite.zip`) continue;
  if (file.endsWith('wordpress.json') || rel === 'baseline-meta.json') {
    const dest = path.join(staging, rel);
    fs.mkdirSync(path.dirname(dest), { recursive: true });
    fs.copyFileSync(file, dest);
  } else if (file.endsWith('wordpress.png')) {
    pngIndex.push({ path: rel, bytes: fs.statSync(file).size });
  }
}

fs.writeFileSync(
  path.join(staging, 'manifest-lite.json'),
  JSON.stringify(
    {
      baselineId: id,
      note: 'JSON-only lite bundle; pixel diffs need full wordpress.png files locally or capture on this machine.',
      pngCount: pngIndex.length,
      pngFiles: pngIndex,
      packagedAt: new Date().toISOString(),
    },
    null,
    2,
  ),
);

fs.rmSync(outZip, { force: true });
const zip = spawnSync('zip', ['-r', outZip, '.'], { cwd: staging, stdio: 'inherit' });
fs.rmSync(staging, { recursive: true, force: true });

if (zip.status !== 0) {
  console.error('zip failed (install zip on macOS/Linux)');
  process.exit(zip.status || 1);
}

const mb = (fs.statSync(outZip).size / (1024 * 1024)).toFixed(1);
console.log(`Wrote ${outZip} (${mb} MB)`);
console.log('Share via drive or attach to agent chat; cloud agents can run text/meta parity from JSON without PNGs.');
