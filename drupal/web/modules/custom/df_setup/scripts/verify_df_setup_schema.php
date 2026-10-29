<?php

/**
 * @file
 * Integrator gate: df_setup schema matches max hook; update 10027 ran via updb.
 *
 * Usage: drush php:script modules/custom/df_setup/scripts/verify_df_setup_schema.php
 */

require_once DRUPAL_ROOT . '/modules/custom/df_setup/df_setup.install';

$max = 0;
foreach (get_defined_functions()['user'] as $fn) {
  if (preg_match('/^df_setup_update_(\d+)$/', $fn, $m)) {
    $max = max($max, (int) $m[1]);
  }
}

$schema = (int) \Drupal::keyValue('system.schema')->get('df_setup');
$kv = \Drupal::keyValue('df_setup');
$applied_10027 = $kv->get('update_10027_applied');

echo "df_setup schema={$schema} max_hook={$max}\n";
if ($schema !== $max) {
  echo "FAIL: schema must equal max hook (run drush updb or fix key_value).\n";
  exit(1);
}

if ($max >= 10027 && !$applied_10027) {
  echo "WARN: schema>=10027 but update_10027_applied marker missing.\n";
  echo "      #22 backfill may have been skipped (wrong hook at 10027). Recovery:\n";
  echo "        drush php:eval '\\Drupal::keyValue(\"system.schema\")->set(\"df_setup\", 10026);'\n";
  echo "        drush updb -y\n";
  echo "      Or replay: drush scr .../test_issue_fixture_replay.php\n";
  exit(1);
}

if ($applied_10027) {
  echo "update_10027_applied=" . date('c', (int) $applied_10027) . "\n";
}
echo "OK\n";
