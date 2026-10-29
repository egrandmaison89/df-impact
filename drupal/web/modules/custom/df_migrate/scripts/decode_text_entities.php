<?php

/**
 * @file
 * CLI: drush php:script modules/custom/df_migrate/scripts/decode_text_entities.php
 */

require_once __DIR__ . '/decode_text_entities.inc';

$dry_run = in_array('--dry-run', $_SERVER['argv'] ?? [], TRUE);
$result = df_migrate_decode_text_entities_run($dry_run);
print_r($result);
