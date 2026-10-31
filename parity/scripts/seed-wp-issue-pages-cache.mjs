#!/usr/bin/env node
/**
 * Copy reference-pack issue desktop HTML into parity/cache/wp-issue-pages (no network).
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { issuePathToCacheFile, validateIssueWpHtml } from '../lib/issue-wp-baseline.mjs';
import { frozenWpHtmlPath } from '../lib/content-sweep-path.mjs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const parityRoot = path.resolve(__dirname, '..');
const packRoot = path.join(parityRoot, 'reference-pack');
const manifest = JSON.parse(
  fs.readFileSync(path.join(parityRoot, 'manifest/site-manifest.json'), 'utf8'),
);

const paths = (manifest.urls || [])
  .filter((e) => e.template === 'issue')
  .map((e) => e.path)
  .filter((p) => p && !/challenge/i.test(p));

let copied = 0;
for (const p of paths) {
  const dest = issuePathToCacheFile(p);
  if (fs.existsSync(dest)) {
    continue;
  }
  const frozen = frozenWpHtmlPath(packRoot, p);
  if (!frozen) {
    continue;
  }
  const html = validateIssueWpHtml(fs.readFileSync(frozen, 'utf8'));
  if (!html) {
    continue;
  }
  fs.mkdirSync(path.dirname(dest), { recursive: true });
  fs.writeFileSync(dest, html);
  copied++;
  process.stderr.write(`seed ${p} from refpack\n`);
}
console.log(JSON.stringify({ copied, total: paths.length }, null, 2));
