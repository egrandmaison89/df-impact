<?php

/**
 * @file
 * Helper: backfill field_issue_banner_is_light from WP reference-pack.
 *
 * Deploy uses df_setup_post_update_issue_banner_is_light(); optional local re-run.
 *
 * Usage: drush scr web/modules/custom/df_migrate/scripts/backfill_issue_banner_is_light.php
 */

declare(strict_types=1);

if (!function_exists('df_setup_post_update_issue_banner_is_light')) {
  require_once DRUPAL_ROOT . '/modules/custom/df_setup/df_setup.post_update.php';
}
if (!function_exists('df_setup_backfill_issue_banner_is_light')) {
  throw new \RuntimeException('Enable df_setup module before running backfill.');
}

print df_setup_post_update_issue_banner_is_light() . "\n";
