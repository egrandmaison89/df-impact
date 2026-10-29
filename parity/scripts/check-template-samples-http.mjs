#!/usr/bin/env node
/**
 * Gate: every path in template-samples.json must return HTTP 200 on Drupal.
 * Run: DRUPAL_BASE_URL=http://df-impact.ddev.site npm run parity:template-samples:http
 */
import fs from 'node:fs';
import http from 'node:http';
import https from 'node:https';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const samples = JSON.parse(
  fs.readFileSync(path.join(parityRoot, 'config', 'template-samples.json'), 'utf8'),
);
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://df-impact.ddev.site').replace(/\/$/, '');

const paths = [...new Set(Object.values(samples).flatMap((e) => e.paths || []))];

function checkOne(pathname) {
  return new Promise((resolve) => {
    const url = new URL(pathname.startsWith('/') ? pathname : `/${pathname}`, `${drupalBase}/`);
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
              ? { path: pathname, status: res.statusCode, ok: true }
              : { path: pathname, status: res.statusCode, ok: false, location },
          );
        });
      },
    );
    req.on('error', (e) => resolve({ path: pathname, status: null, ok: false, error: String(e) }));
    req.setTimeout(45000, () => {
      req.destroy();
      resolve({ path: pathname, status: null, ok: false, error: 'timeout' });
    });
    req.end();
  });
}

async function main() {
  const results = [];
  for (const p of paths) {
    results.push(await checkOne(p));
  }
  const pass = results.filter((r) => r.ok).length;
  const out = {
    outFile: path.join(parityRoot, 'reports', 'template-samples-http', `check-${Date.now()}.json`),
    generatedAt: new Date().toISOString(),
    drupalBase,
    pathCount: paths.length,
    pass,
    all200: pass === paths.length,
    failures: results.filter((r) => !r.ok),
  };
  fs.mkdirSync(path.dirname(out.outFile), { recursive: true });
  fs.writeFileSync(out.outFile, JSON.stringify({ ...out, results }, null, 2));
  console.log(JSON.stringify({ outFile: out.outFile, pathCount: paths.length, pass, all200: out.all200 }, null, 2));
  if (!out.all200) {
    process.exit(1);
  }
}

main();
