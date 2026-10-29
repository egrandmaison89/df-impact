#!/usr/bin/env node
/**
 * Restore wp-images files that fail `file` image check (HTML stubs → CDN).
 */
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, '../..');
const wpDir = path.join(repoRoot, 'drupal/web/sites/default/files/wp-images');
const media = JSON.parse(
  fs.readFileSync(
    path.join(repoRoot, 'drupal/web/modules/custom/df_migrate/data/media.json'),
    'utf8',
  ),
);

const index = new Map();
for (const row of media) {
  const url = row.source_url;
  const name = row.filename;
  if (!url || !name) continue;
  index.set(name.toLowerCase(), url);
  index.set(name.replace(/\.[^.]+$/i, '').toLowerCase(), url);
}

function isImage(path) {
  const r = spawnSync('file', ['-b', path], { encoding: 'utf8' });
  return /^(JPEG|PNG|WebP|GIF|RIFF)/i.test((r.stdout || '').trim());
}

function validBytes(buf) {
  if (buf.length < 128) return false;
  if (buf.includes(Buffer.from('<html'))) return false;
  const h = buf.subarray(0, 12);
  if (h[0] === 0xff && h[1] === 0xd8) return true;
  if (h.toString('ascii', 0, 4) === '\x89PNG') return true;
  if (h.toString('ascii', 0, 4) === 'RIFF') return true;
  if (h.toString('ascii', 0, 3) === 'GIF') return true;
  return false;
}

function fetchUrl(url) {
  for (let attempt = 0; attempt < 3; attempt++) {
    const r = spawnSync(
      'curl',
      ['-sS', '-L', '--max-time', '20', '-A', 'DFMigrate/restore', url],
      { encoding: 'buffer', maxBuffer: 30 * 1024 * 1024 },
    );
    if (r.status === 0 && r.stdout && validBytes(r.stdout)) {
      return r.stdout;
    }
  }
  return null;
}

function restoreOne(filePath) {
  const base = path.basename(filePath);
  const stem = base.replace(/\.[^.]+$/i, '').toLowerCase();
  const urls = [];
  if (index.has(base.toLowerCase())) urls.push(index.get(base.toLowerCase()));
  if (index.has(stem)) urls.push(index.get(stem));
  if (urls.length === 0) {
    for (const prefix of ['2026/08/', '2026/06/', '2024/06/', '2023/03/', '2023/01/']) {
      urls.push(`https://i0.wp.com/danafarberimpact.org/wp-content/uploads/${prefix}${encodeURIComponent(base)}?ssl=1`);
    }
  }
  for (const u of [...new Set(urls)].slice(0, 6)) {
    let buf = fetchUrl(u);
    if (buf) {
      fs.writeFileSync(filePath, buf);
      return 'remote';
    }
    if (u.includes('danafarberimpact.org/wp-content/uploads/')) {
      const m = u.match(/wp-content\/uploads\/(.+?)(\?|$)/);
      if (m) {
        buf = fetchUrl(`https://i0.wp.com/danafarberimpact.org/wp-content/uploads/${m[1]}?ssl=1`);
        if (buf) {
          fs.writeFileSync(filePath, buf);
          return 'photon';
        }
      }
    }
  }
  const rel = `drupal/web/sites/default/files/wp-images/${base}`;
  const git = spawnSync('git', ['-C', repoRoot, 'show', `origin/cursor/templates-inbrief-archives-185b:${rel}`], {
    encoding: 'buffer',
    maxBuffer: 50 * 1024 * 1024,
  });
  if (git.status === 0 && validBytes(git.stdout)) {
    fs.writeFileSync(filePath, git.stdout);
    return 'git';
  }
  return null;
}

const bad = fs.readdirSync(wpDir).filter((f) => {
  const p = path.join(wpDir, f);
  return fs.statSync(p).isFile() && !isImage(p);
});

const stats = { bad: bad.length, restored: 0, failed: 0 };
for (let idx = 0; idx < bad.length; idx++) {
  const p = path.join(wpDir, bad[idx]);
  const r = restoreOne(p);
  if (r) stats.restored++;
  else {
    stats.failed++;
    console.error('failed:', bad[idx]);
  }
  if (idx % 10 === 0) console.error(`progress ${idx + 1}/${bad.length}`);
}
console.log(JSON.stringify(stats, null, 2));
