import fs from 'node:fs';
import path from 'node:path';
import { caseSlug } from './manifest-urls.mjs';

export function baselineDir(parityRoot, baselineId) {
  return path.join(parityRoot, 'baselines', baselineId);
}

export function baselineCaseDir(parityRoot, baselineId, pathNorm, breakpointLabel) {
  const slug = caseSlug(pathNorm);
  return path.join(baselineDir(parityRoot, baselineId), slug, breakpointLabel);
}

export function baselineMetaPath(parityRoot, baselineId) {
  return path.join(baselineDir(parityRoot, baselineId), 'baseline-meta.json');
}

export function loadBaselineCase(parityRoot, baselineId, pathNorm, breakpointLabel) {
  const dir = baselineCaseDir(parityRoot, baselineId, pathNorm, breakpointLabel);
  const jsonPath = path.join(dir, 'wordpress.json');
  const pngPath = path.join(dir, 'wordpress.png');
  if (!fs.existsSync(jsonPath) || !fs.existsSync(pngPath)) {
    return null;
  }
  const wp = JSON.parse(fs.readFileSync(jsonPath, 'utf8'));
  return { dir, wp, pngPath, jsonPath };
}

export function copyBaselineScreenshot(pngPath, destPath) {
  fs.mkdirSync(path.dirname(destPath), { recursive: true });
  fs.copyFileSync(pngPath, destPath);
}
