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

const articles = JSON.parse(
  fs.readFileSync(
    path.join(repoRoot, 'drupal/web/modules/custom/df_migrate/data/articles.json'),
    'utf8',
  ),
);
const articleImageByStem = new Map();
for (const row of articles) {
  const url = row.featured_image_url;
  if (!url || typeof url !== 'string') continue;
  const stem = path.basename(url.replace(/\/$/, '')).toLowerCase();
  if (stem) articleImageByStem.set(stem, url.replace(/\/$/, ''));
}

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
  if (h[0] === 0x89 && h[1] === 0x50 && h[2] === 0x4e && h[3] === 0x47) return true;
  if (h[0] === 0x52 && h[1] === 0x49 && h[2] === 0x46 && h[3] === 0x46) return true;
  if (h[0] === 0x47 && h[1] === 0x49 && h[2] === 0x46) return true;
  return false;
}

function photonUrl(uploadsPath) {
  const clean = uploadsPath.replace(/^\//, '');
  return `https://i0.wp.com/danafarberimpact.org/wp-content/uploads/${clean}?ssl=1`;
}

function fetchUrl(url) {
  const candidates = [];
  const m = url.match(/wp-content\/uploads\/(.+?)(\?|$)/);
  if (m) {
    candidates.push(photonUrl(m[1]));
  }
  candidates.push(url);
  for (const tryUrl of [...new Set(candidates)]) {
    for (let attempt = 0; attempt < 3; attempt++) {
      const r = spawnSync(
        'curl',
        ['-sS', '-L', '--max-time', '25', '-A', 'DFMigrate/restore', tryUrl],
        { encoding: 'buffer', maxBuffer: 30 * 1024 * 1024 },
      );
      if (r.status === 0 && r.stdout && validBytes(r.stdout)) {
        return r.stdout;
      }
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
  const stemNoSize = stem.replace(/-\d+x\d+$/, '');
  if (stemNoSize !== stem && index.has(stemNoSize)) {
    urls.push(index.get(stemNoSize));
  }
  if (/1450-1024x734/.test(base)) {
    urls.push(photonUrl(`2025/06/${base}`));
    urls.push(photonUrl(`2025/06/${base.replace('-1024x734', '')}.jpg`));
  }
  if (articleImageByStem.has(stem)) {
    const raw = articleImageByStem.get(stem);
    urls.push(raw.startsWith('http') ? raw : `https://danafarberimpact.org/${raw.replace(/^\//, '')}`);
  }
  if (urls.length === 0) {
    for (const prefix of [
      '2026/09/',
      '2026/08/',
      '2026/06/',
      '2025/09/',
      '2024/09/',
      '2024/07/',
      '2024/06/',
      '2023/11/',
      '2023/03/',
      '2023/01/',
      '2022/11/',
    ]) {
      urls.push(photonUrl(`${prefix}${base}`));
      if (!/\.[a-z0-9]+$/i.test(base)) {
        for (const ext of ['.jpg', '.png', '.webp']) {
          urls.push(photonUrl(`${prefix}${base}${ext}`));
        }
      }
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
