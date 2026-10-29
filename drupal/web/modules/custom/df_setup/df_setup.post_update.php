<?php

/**
 * @file
 * Post-update hooks for df_setup (run after schema 10035 on phase4).
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Publishes Home and sets page.front when 10035 ran before moderation fix.
 */
function df_setup_post_update_ensure_home_landing_published(): string {
  require_once __DIR__ . '/df_setup.install';
  _df_setup_ensure_home_landing();
  return 'Home landing published; page.front verified.';
}

/**
 * Ensures in_brief has editable field_excerpt for archive listing teasers.
 */
function df_setup_post_update_in_brief_excerpt(): string {
  if (FieldConfig::loadByName('node', 'in_brief', 'field_excerpt')) {
    return 'in_brief field_excerpt already present.';
  }
  $storage = FieldStorageConfig::loadByName('node', 'field_excerpt');
  if (!$storage instanceof FieldStorageConfig) {
    return 'field_excerpt storage missing; sync field.storage.node.field_excerpt from config.';
  }
  FieldConfig::create([
    'field_storage' => $storage,
    'bundle' => 'in_brief',
    'label' => 'Listing excerpt',
    'required' => FALSE,
  ])->save();
  return 'Added field_excerpt to in_brief.';
}

/**
 * Seeds article/in_brief field_excerpt from archive_row_wp_excerpts.json.
 */
function df_setup_post_update_seed_listing_excerpts(): string {
  $script = __DIR__ . '/../df_migrate/scripts/backfill_archive_row_excerpts.php';
  if (!is_readable($script)) {
    return 'backfill_archive_row_excerpts.php missing';
  }
  require_once $script;
  $result = df_migrate_backfill_archive_row_excerpts();
  if (!empty($result['error'])) {
    return 'Archive excerpt backfill failed: ' . $result['error'];
  }
  return sprintf(
    'Listing excerpt backfill: %d updated, %d already matched, %d unresolved.',
    (int) ($result['updated'] ?? 0),
    (int) ($result['skipped'] ?? 0),
    (int) ($result['missing'] ?? 0),
  );
}

/**
 * Restores in_brief field_summary HTML from in_brief.json export bodies.
 */
function df_setup_post_update_restore_in_brief_summary(): string {
  $restore = __DIR__ . '/../df_migrate/scripts/restore_in_brief_summary_from_export.php';
  if (!is_readable($restore)) {
    return 'restore_in_brief_summary_from_export.php missing';
  }
  require_once $restore;
  $fixed = df_migrate_restore_in_brief_summary_from_export();
  if (!empty($fixed['error'])) {
    return 'in_brief summary restore failed: ' . $fixed['error'];
  }
  drupal_flush_all_caches();
  return sprintf(
    'in_brief field_summary restored from export: %d updated.',
    (int) ($fixed['updated'] ?? 0),
  );
}
