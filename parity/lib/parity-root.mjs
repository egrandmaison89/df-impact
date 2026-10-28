/**
 * Resolve parity package root without import.meta (Playwright may compile specs as CJS).
 */
import fs from 'node:fs';
import path from 'node:path';

function hasManifest(dir) {
  return fs.existsSync(path.join(dir, 'manifest', 'site-manifest.json'));
}

/**
 * @param {string} [cwd] Defaults to process.cwd()
 * @returns {string} Absolute path to parity/ (manifest + baselines live here)
 */
export function resolveParityRoot(cwd = process.cwd()) {
  const fromEnv = process.env.PARITY_ROOT;
  if (fromEnv && hasManifest(fromEnv)) {
    return path.resolve(fromEnv);
  }
  if (hasManifest(cwd)) {
    return path.resolve(cwd);
  }
  const nested = path.join(cwd, 'parity');
  if (hasManifest(nested)) {
    return path.resolve(nested);
  }
  return path.resolve(cwd);
}
