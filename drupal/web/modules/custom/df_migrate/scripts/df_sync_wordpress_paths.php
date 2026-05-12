<?php

/**
 * @file
 * Legacy entrypoint — Drush php:script often does not bootstrap Drupal in Drush 13+.
 *
 * Use instead:
 *   drush df-migrate:sync-wordpress-paths
 */

fwrite(STDERR, "Use: drush df-migrate:sync-wordpress-paths\n");
exit(1);
