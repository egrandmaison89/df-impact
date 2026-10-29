#!/usr/bin/env node
/** Assert every path in template-samples.json returns HTTP 200 on Drupal (no redirect). */
import fs from 'node:fs';
import http from 'node:http';
import https from 'node:https';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const base = (process.env.DRUPAL_BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const samples = JSON.parse(
  fs.readFileSync(path.join(parityRoot, 'config', 'template-samples.json'), 'utf8'),
);
const paths = [...new Set(Object.values(samples).flatMap((entry) => entry.paths || []))];

function checkPath(pathname) {
  return new Promise((resolve) => {
    const url = new URL(pathname.startsWith('/') ? pathname : `/${pathname}`, `${base}/`);
    const lib = url.protocol === 'https:' ? https : http;
    const req = lib.request(
      url,
      { method: 'GET', headers: { Accept: 'text/html' } },
      (res) => {
        res.resume();
        res.on('end', () => {
          const location = res.headers.location || null;
          const ok = res.statusCode === 200 && !location;
          resolve(
            ok
              ? null
              : { path: pathname, status: res.statusCode, location },
          );
        });
      },
    );
    req.on('error', (e) => resolve({ path: pathname, error: String(e) }));
    req.setTimeout(60000, () => {
      req.destroy();
      resolve({ path: pathname, error: 'timeout' });
    });
    req.end();
  });
}

const failures = [];
for (const p of paths) {
  const failure = await checkPath(p);
  if (failure) {
    failures.push(failure);
  }
}

const pass = paths.length - failures.length;
const out = {
  drupalBase: base,
  checked: paths.length,
  pass,
  all200: failures.length === 0,
  failures,
};
console.log(JSON.stringify(out, null, 2));
process.exit(failures.length === 0 ? 0 : 1);
