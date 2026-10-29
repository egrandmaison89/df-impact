import fs from 'node:fs';
import path from 'node:path';

const parityRoot = path.resolve(process.cwd());

/** Drupal aliases that differ from template-samples.json labels. */
export function normalizeBehaviorPath(p) {
  const x = p.trim();
  if (x === '/about') return '/about-impact';
  if (x === '/home' || x === '/') return '/homepage';
  return x.startsWith('/') ? x : `/${x}`;
}

/** All template sample paths use wp-chrome-header (article, issue, in-brief, archive, landing). */
export function pathUsesChromeHeader(pathNorm) {
  const p = normalizeBehaviorPath(pathNorm);
  return p.length > 0 && p !== '/';
}

export function expectedBehaviorCaseCount(pathList) {
  return pathList.length * 3;
}

export function loadTemplateSamplePaths() {
  const raw = JSON.parse(
    fs.readFileSync(path.join(parityRoot, 'config', 'template-samples.json'), 'utf8'),
  );
  const set = new Set();
  for (const entry of Object.values(raw)) {
    for (const p of entry.paths || []) {
      set.add(normalizeBehaviorPath(p));
    }
  }
  return [...set];
}
