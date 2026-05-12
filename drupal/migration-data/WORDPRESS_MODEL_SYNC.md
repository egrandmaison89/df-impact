# WordPress model → Drupal sync (plan & runbook)

WordPress at **danafarberimpact.org** is the source of truth for migrated magazine stories. Drupal keeps a JSON export under `web/modules/custom/df_migrate/data/` and migrate map tables (`migrate_map_df_articles`, `migrate_map_df_in_brief`) linking WordPress IDs to node IDs.

## Goals

1. **Sitemap truth set** — Know which *post* URLs WordPress exposes today (core sitemaps: `wp-sitemap-posts-post-*.xml`).
2. **Fresh REST export** — Regenerate `articles.json`, `in_brief.json`, `redirects.json`, taxonomies, and `media.json` from live WordPress.
3. **Prune Drupal** — Remove migrated article / in-brief nodes whose slug no longer appears in the sitemap (and clear their migrate map rows). Native Drupal content (no map row) is never deleted.
4. **Re-import / backfill** — Run migrations with `--update`, path/sync script, featured image backfill, cache rebuild.

## Implementation (in this repo)

| Piece | Location |
|-------|-----------|
| Fetch sitemap + REST, run processor, copy JSON | `drupal/migration-data/fetch_wordpress_model.py` |
| Build migrate JSON from raw WP exports | `drupal/migration-data/process_wp_data.py` (dynamic `posts_page_*` / `media_page_*` globs) |
| Sitemap slug set for pruning | `web/modules/custom/df_migrate/data/wp_sitemap_state.json` (generated) |
| Prune orphans (dry run by default) | `drush df-migrate:prune-wordpress-orphans` / `--execute` |
| Path aliases, placement, redirects | `drush df-migrate:sync-wordpress-paths` |
| Featured images from JSON | `drush df-migrate:backfill-article-featured` |

## Runbook (typical)

Run from **host** (network to WordPress):

```bash
cd drupal/migration-data
python3 fetch_wordpress_model.py
```

Options:

- `--sitemap-only` — Only writes `wp_sitemap_state.json` (fast; use before prune without full REST pull).
- `--skip-process` — Downloads raw JSON only; run `python3 process_wp_data.py` and copy outputs yourself.

Override base URL: `WP_BASE_URL=https://staging.example.org python3 fetch_wordpress_model.py`

Then from **Drupal** (e.g. DDEV):

```bash
ddev drush migrate:import df_articles --update
ddev drush migrate:import df_in_brief --update
ddev drush df-migrate:prune-wordpress-orphans              # list orphans
ddev drush df-migrate:prune-wordpress-orphans --execute    # delete after review
ddev drush df-migrate:sync-wordpress-paths
ddev drush df-migrate:backfill-article-featured
ddev drush cache:rebuild
```

Re-index search if you use Search API: `ddev drush sapi-i` (or your project’s indexer).

## Risks / notes

- **Sitemap vs REST**: The sitemap drives *deletion* (slug set). Content updates come from REST + `articles.json`. Run the full fetch before prune so imports match WordPress.
- **Caches/CDN**: Sitemaps can lag; review dry-run output before `--execute`.
- **Redirects**: After deletes, review redirect entities or run path sync; broken redirects may need manual cleanup.
- **Not removed**: Nodes without a migrate map row (hand-built in Drupal) are ignored by prune.

## Plan summary

1. Run `fetch_wordpress_model.py` to refresh sitemap state and JSON.
2. `migrate:import … --update` for articles and in-brief.
3. Dry-run prune; then `--execute` if the list matches expectations.
4. Sync paths / backfill images / rebuild caches.
