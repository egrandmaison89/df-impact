<?php

/**
 * @file
 * Legacy entrypoint — Drush 13+ often does not bootstrap `drush scr` scripts.
 *
 * Use the Drush command instead (from the drupal directory):
 *
 * @code
 *   ./vendor/bin/drush -r web df-migrate:backfill-article-featured
 *   ./vendor/bin/drush -r web df-migrate:backfill-article-featured --dry-run
 *   ./vendor/bin/drush -r web df-migrate:backfill-article-featured --live-fallback
 *   ./vendor/bin/drush -r web df-migrate:backfill-article-featured --only-wp-id=9343
 * @endcode
 */

fwrite(
  STDERR,
  "drush scr does not reliably bootstrap Drupal in Drush 13; use:\n" .
  "  ./vendor/bin/drush -r web df-migrate:backfill-article-featured\n" .
  "  ./vendor/bin/drush -r web df-migrate:backfill-article-featured --dry-run\n",
);
exit(1);
