import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { normalizePath } from './content-sweep-path.mjs';
import { repoRoot } from './manifest-urls.mjs';

const fixturePath = path.join(
  repoRoot,
  'drupal/web/modules/custom/df_migrate/data/issue_page_wp_fixture.json',
);

/** @type {Map<string, { wp_archive_error?: boolean }> | null} */
let byAlias = null;

function loadByAlias() {
  if (byAlias) {
    return byAlias;
  }
  byAlias = new Map();
  if (!fs.existsSync(fixturePath)) {
    return byAlias;
  }
  const data = JSON.parse(fs.readFileSync(fixturePath, 'utf8'));
  for (const row of data.issues ?? []) {
    if (typeof row.path_alias === 'string') {
      byAlias.set(normalizePath(row.path_alias), row);
    }
  }
  return byAlias;
}

export function issueFixtureForPath(publicPath) {
  return loadByAlias().get(normalizePath(publicPath)) ?? null;
}

export function issueHasWpArchiveError(publicPath) {
  const row = issueFixtureForPath(publicPath);
  return Boolean(row?.wp_archive_error);
}
