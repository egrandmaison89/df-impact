<?php

/**
 * @file
 * Integrator gate: schema frozen at 10035; all df_setup post_updates applied.
 *
 * Usage: drush php:script modules/custom/df_setup/scripts/verify_df_setup_post_updates.php
 *
 * Run on a DB already at hook 10035 before/after merges that only add post_updates.
 */

require_once DRUPAL_ROOT . '/modules/custom/df_setup/df_setup.install';

const DF_SETUP_SCHEMA_FROZEN = 10035;

$max = 0;
foreach (get_defined_functions()['user'] as $fn) {
  if (preg_match('/^df_setup_update_(\d+)$/', $fn, $m)) {
    $max = max($max, (int) $m[1]);
  }
}

$schema = (int) \Drupal::keyValue('system.schema')->get('df_setup');

echo "df_setup schema={$schema} max_numbered_hook={$max} (frozen cap " . DF_SETUP_SCHEMA_FROZEN . ")\n";

if ($max > DF_SETUP_SCHEMA_FROZEN) {
  echo "FAIL: numbered hook above " . DF_SETUP_SCHEMA_FROZEN . " — use df_setup.post_update.php instead.\n";
  exit(1);
}

if ($schema !== DF_SETUP_SCHEMA_FROZEN) {
  echo "FAIL: integrator DB must be at schema " . DF_SETUP_SCHEMA_FROZEN . " (run updb from 10034-era or fresh migrate).\n";
  exit(1);
}

if ($max !== DF_SETUP_SCHEMA_FROZEN) {
  echo "FAIL: phase4 must define numbered hooks through " . DF_SETUP_SCHEMA_FROZEN . " only.\n";
  exit(1);
}

/** @var \Drupal\Core\Update\UpdateRegistry $registry */
$registry = \Drupal::service('update.post_update_registry');
$pending = array_values(array_filter(
  $registry->getPendingUpdateFunctions(),
  static fn (string $f): bool => str_starts_with($f, 'df_setup_post_update_'),
));

if ($pending !== []) {
  echo "FAIL: pending df_setup post_updates:\n";
  foreach ($pending as $fn) {
    echo "  - {$fn}\n";
  }
  echo "Run: drush updb -y\n";
  exit(1);
}

echo "post_updates: none pending\nOK\n";
