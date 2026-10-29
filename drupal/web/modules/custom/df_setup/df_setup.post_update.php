<?php

/**
 * @file
 * Name-based post updates for df_setup after schema **10035** (frozen).
 *
 * Do not add df_setup_update_10036+ in df_setup.install. Drupal tracks
 * post_update hooks by function name, so ordering merges no longer skips work.
 *
 * Combined on phase4 integrator:
 * - **#23** content-sweep: in_brief excerpts + home moderation fix.
 * - **#25** cover / media seed for issue parity.
 * - **#10** `is_light` field: config import + backfill post-update (stacked on #25).
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
    'label' => 'Listing excerpt',
    'bundle' => 'in_brief',
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

/**
 * Seed issue field_cover_image from committed wp-images / WP uploads (browse-issues).
 */
function df_setup_post_update_seed_issue_covers(&$sandbox): string {
  return _df_setup_backfill_issue_cover_images();
}

/**
 * Seed issue field_banner_image when empty (issue landing hero parity).
 */
function df_setup_post_update_seed_issue_banners(&$sandbox): string {
  return _df_setup_backfill_issue_banner_images();
}

/**
 * Applies full About Impact Gutenberg body + staff block (#25 on phase4).
 */
function df_setup_post_update_ensure_about_impact_body(): string {
  require_once __DIR__ . '/df_setup.install';
  return _df_setup_apply_about_impact_gutenberg_body();
}

/**
 * Point Late Fall 2022 in-content links at /tag/late-fall-2022 like live WP.
 */
function df_setup_post_update_late_fall_2022_tag_links(): string {
  require_once __DIR__ . '/df_setup.install';
  return _df_setup_apply_late_fall_2022_link_parity();
}

/**
 * Replaces Late Fall 2022 bylines that were nested inside wp:image blocks.
 */
function df_setup_post_update_late_fall_2022_byline_placement(): string {
  require_once __DIR__ . '/df_setup.install';
  return _df_setup_apply_late_fall_2022_link_parity();
}

/**
 * Drops body bylaws where export has no tag link (template carries issue label).
 */
function df_setup_post_update_late_fall_2022_export_byline_trim(): string {
  require_once __DIR__ . '/df_setup.install';
  return _df_setup_apply_late_fall_2022_link_parity();
}
