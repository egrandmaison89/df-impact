#!/usr/bin/env node
/**
 * HTTP gate: every path in parity/config/template-samples.json returns 200 on Drupal.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const samples = JSON.parse(
  fs.readFileSync(path.join(parityRoot, 'config', 'template-samples.json'), 'utf8'),
);
const drupalBase = (process.env.DRUPAL_BASE_URL || 'http://df-impact.ddev.site').replace(/\/$/, '');

const paths = [...new Set(Object.values(samples).flatMap((e) => e.paths || []))];

async function checkOne(p) {
  const url = `${drupalBase}${p.startsWith('/') ? p : `/${p}`}`;
  try {
    const r = await fetch(url, { redirect: 'manual', signal: AbortSignal.timeout(45000) });
    await r.arrayBuffer();
    return { path: p, status: r.status, ok: r.status === 200 };
  } catch (e) {
    return { path: p, status: null, ok: false, error: String(e) };
  }
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
  if (!out.all200) process.exit(1);
}

main();
