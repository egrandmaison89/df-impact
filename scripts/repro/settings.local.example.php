<?php

/**
 * Copy to web/sites/default/settings.local.php (gitignored).
 * Enables host-side Drush when DB is reached via DDEV published port.
 */

$databases['default']['default'] = [
  'database' => 'db',
  'username' => 'db',
  'password' => 'db',
  'host' => '127.0.0.1',
  'port' => (int) (getenv('DDEV_DB_PORT') ?: '3306'),
  'driver' => 'mysql',
  'prefix' => '',
  'collation' => 'utf8mb4_general_ci',
];

// Copy from settings.ddev.php after `ddev start`, or from `drush php:eval "echo \Drupal::service('private_key')->get();"`.
$settings['hash_salt'] = getenv('DRUPAL_HASH_SALT') ?: 'local-dev-only-change-me';

$settings['config_sync_directory'] = 'sites/default/files/sync';
$settings['trusted_host_patterns'] = ['.*'];

$config['system.performance']['css']['preprocess'] = FALSE;
$config['system.performance']['js']['preprocess'] = FALSE;
