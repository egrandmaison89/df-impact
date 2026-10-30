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
 * - **#30** internal links, inline images, featured media repair.
 */

declare(strict_types=1);

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Ensures df_secondary is enabled so /search matches WP (update_10034 on fresh VMs).
 */
function df_setup_post_update_ensure_df_secondary_search(): string {
  if (\Drupal::moduleHandler()->moduleExists('df_secondary')) {
    return 'df_secondary already enabled.';
  }
  \Drupal::service('module_installer')->install(['df_secondary']);
  drupal_flush_all_caches();
  return 'Enabled df_secondary for /search parity.';
}

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
  _df_setup_repair_link_default_view_formatters();
  FieldConfig::create([
    'field_storage' => $storage,
    'label' => 'Listing excerpt',
    'bundle' => 'in_brief',
    'required' => FALSE,
  ])->save();
  return 'Added field_excerpt to in_brief.';
}

/**
 * Stale view displays may use link widget id "link_default" as formatter (invalid).
 *
 * FieldConfig::save() validates all displays; repair before post_updates that add fields.
 */
function _df_setup_repair_link_default_view_formatters(): void {
  if (!\Drupal::moduleHandler()->moduleExists('link')) {
    \Drupal::service('module_installer')->install(['link'], TRUE);
  }
  $storage = \Drupal::entityTypeManager()->getStorage('entity_view_display');
  foreach ($storage->loadMultiple() as $display) {
    if (!$display instanceof EntityViewDisplayInterface) {
      continue;
    }
    $content = $display->get('content');
    if (!is_array($content)) {
      continue;
    }
    $changed = FALSE;
    foreach ($content as $field_name => $component) {
      if (!is_array($component)) {
        continue;
      }
      if (($component['type'] ?? '') === 'link_default') {
        $content[$field_name]['type'] = 'link';
        $changed = TRUE;
      }
    }
    if ($changed) {
      $display->set('content', $content);
      $display->save();
    }
  }
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
function df_setup_post_update_seed_issue_covers(&$sandbox = NULL): string {
  return _df_setup_backfill_issue_cover_images();
}

/**
 * Seed issue field_banner_image when empty (issue landing hero parity).
 */
function df_setup_post_update_seed_issue_banners(&$sandbox = NULL): string {
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

/**
 * Converges About + truncated article bodies for content-sweep parity (any DB state).
 */
function df_setup_post_update_converge_content_sweep_reference_bodies(): string {
  require_once __DIR__ . '/df_setup.install';
  return _df_setup_converge_content_sweep_reference_bodies();
}

/**
 * Rewrites WP internal links, localizes inline images, fixes broken featured media.
 */
function df_setup_post_update_repair_internal_links_and_media(): string {
  $script = __DIR__ . '/../df_migrate/scripts/df_setup_content_repair.php';
  if (!is_readable($script)) {
    return 'df_setup_content_repair.php missing';
  }
  require_once $script;
  $result = df_setup_content_repair_run();
  return sprintf(
    'Content repair: link fields %d/%d, inline images %d/%d, featured %d updated / %d cleared (scanned %d), lead body resync %d.',
    (int) ($result['link_fields_updated'] ?? 0),
    (int) ($result['fields_scanned'] ?? 0),
    (int) ($result['inline_nodes_updated'] ?? 0),
    (int) ($result['inline_nodes_scanned'] ?? 0),
    (int) ($result['featured_updated'] ?? 0),
    (int) ($result['featured_cleared'] ?? 0),
    (int) ($result['featured_scanned'] ?? 0),
    (int) ($result['lead_body_resync'] ?? 0),
  );
}

/**
 * Installs issue banner is-light field config and backfills from reference-pack.
 */
function df_setup_post_update_issue_banner_is_light(): string {
  $messages = [df_setup_import_issue_banner_is_light_config()];
  $messages[] = df_setup_ensure_issue_banner_is_light_form_display();
  $messages[] = df_setup_backfill_issue_banner_is_light();
  return implode(' ', array_filter($messages));
}

/**
 * Imports field storage + field config from this module's config/install (idempotent).
 */
function df_setup_import_issue_banner_is_light_config(): string {
  if (!\Drupal::moduleHandler()->moduleExists('field')) {
    return 'Field module unavailable.';
  }
  $module_path = \Drupal::service('extension.list.module')->getPath('df_setup');
  $install_storage = new FileStorage($module_path . '/config/install');
  $installed = [];

  if (!FieldStorageConfig::loadByName('node', 'field_issue_banner_is_light')) {
    $data = $install_storage->read('field.storage.node.field_issue_banner_is_light');
    if ($data) {
      FieldStorageConfig::create($data)->save();
      $installed[] = 'storage';
    }
  }

  if (!FieldConfig::loadByName('node', 'issue', 'field_issue_banner_is_light')) {
    $data = $install_storage->read('field.field.node.issue.field_issue_banner_is_light');
    if ($data) {
      FieldConfig::create($data)->save();
      $installed[] = 'field';
    }
  }

  if ($installed !== []) {
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
    return 'Imported field_issue_banner_is_light ' . implode('+', $installed) . '.';
  }
  return 'field_issue_banner_is_light config already present.';
}

/**
 * Sets issue cover alt text to the WP /browse-issues/ alt (QA-004).
 */
function df_setup_post_update_browse_issue_cover_alt(): string {
  // WP leaves /spring-2025-issue and /fall-2024-issue covers without alt; keep ours.
  $alts = [
    '/fall-2026-issue' => 'Cover of the Fall 2026 issue of Impact',
    '/summer-2026-issue' => 'Cover of the Summer 2026 issue of Impact',
    '/spring-2026-issue' => 'Impact Spring 2026 magazine cover',
    '/winter-2025-issue' => 'Impact Winter 2025 cover',
    '/fall-2025-issue' => 'Impact Fall 2025 cover',
    '/summer-2025-issue' => 'Impact Summer 2025 cover',
    '/winter-2024-issue' => 'Impact Winter 2024 cover',
    '/summer-2024-issue' => 'Impact Magazine Summer 2024 Cover',
    '/spring-2024-issue' => 'Dana-Farber Impact Spring 2024 cover',
    '/winter-2023-issue' => 'Dana-Farber Impact Winter 2023 magazine cover',
    '/fall-2023-issue' => 'Impact Fall 2023 Cover',
    '/summer-2023-issue' => 'Impact Summer 2023 Cover',
    '/spring-2023-issue' => 'Impact Spring 2023 Cover',
  ];
  $alias_manager = \Drupal::service('path_alias.manager');
  $node_storage = \Drupal::entityTypeManager()->getStorage('node');
  $updated = 0;
  foreach ($alts as $alias => $alt) {
    $path = $alias_manager->getPathByAlias($alias);
    if (!preg_match('#^/node/(\d+)$#', $path, $m)) {
      continue;
    }
    $issue = $node_storage->load((int) $m[1]);
    if (!$issue || $issue->bundle() !== 'issue' || !$issue->hasField('field_cover_image')) {
      continue;
    }
    $media = $issue->get('field_cover_image')->entity;
    if (!$media || !$media->hasField('field_media_image') || $media->get('field_media_image')->isEmpty()) {
      continue;
    }
    $item = $media->get('field_media_image')->first();
    if ((string) $item->alt === $alt) {
      continue;
    }
    $item->alt = $alt;
    $media->save();
    $updated++;
  }
  return sprintf('Browse-issues cover alt text: %d updated.', $updated);
}

/**
 * Attach In Brief featured images (in_brief.json holds WP attachment-page URLs, not files).
 */
function df_setup_post_update_in_brief_featured_images(): string {
  if (!\Drupal::moduleHandler()->moduleExists('df_migrate') || !class_exists('\Drupal\df_migrate\Plugin\migrate\process\DownloadImage')) {
    return 'In Brief featured images: df_migrate unavailable.';
  }
  $data_dir = DRUPAL_ROOT . '/' . \Drupal::service('extension.list.module')->getPath('df_migrate') . '/data';
  $briefs = json_decode((string) @file_get_contents($data_dir . '/in_brief.json'), TRUE);
  $media_rows = json_decode((string) @file_get_contents($data_dir . '/media.json'), TRUE);
  if (!is_array($briefs) || !is_array($media_rows)) {
    return 'In Brief featured images: data files missing.';
  }
  $media_by_wp_id = [];
  foreach ($media_rows as $row) {
    if (!empty($row['wp_id']) && !empty($row['source_url'])) {
      $media_by_wp_id[(string) $row['wp_id']] = $row;
    }
  }
  $alias_manager = \Drupal::service('path_alias.manager');
  $node_storage = \Drupal::entityTypeManager()->getStorage('node');
  $file_system = \Drupal::service('file_system');
  $updated = 0;
  $missing = 0;
  foreach ($briefs as $brief) {
    if (empty($brief['drupal_alias'])) {
      continue;
    }
    $path = $alias_manager->getPathByAlias($brief['drupal_alias']);
    if (!preg_match('#^/node/(\d+)$#', $path, $m)) {
      continue;
    }
    $node = $node_storage->load((int) $m[1]);
    if (!$node || $node->bundle() !== 'in_brief' || !$node->hasField('field_image') || !$node->get('field_image')->isEmpty()) {
      continue;
    }
    $media_row = $media_by_wp_id[(string) ($brief['featured_image_wp_id'] ?? '')] ?? NULL;
    $url = $media_row['source_url'] ?? '';
    if ($url === '' && preg_match('#<img[^>]+src="([^"]*wp-content/uploads/[^"]+)"#', (string) ($brief['body'] ?? ''), $img)) {
      $url = $img[1];
    }
    $uri = $url !== '' ? \Drupal\df_migrate\Plugin\migrate\process\DownloadImage::urlToUri($url) : '';
    $real = $uri !== '' ? $file_system->realpath($uri) : FALSE;
    if (!is_string($real) || !is_readable($real)) {
      $missing++;
      continue;
    }
    $fid = \Drupal\df_migrate\Plugin\migrate\process\DownloadImage::getOrCreateFileFromUrl($url);
    if (!$fid) {
      $missing++;
      continue;
    }
    $alt = trim((string) ($media_row['alt_text'] ?? '')) ?: $node->label();
    $node->set('field_image', ['target_id' => $fid, 'alt' => $alt]);
    $node->save();
    $updated++;
  }
  return sprintf('In Brief featured images: %d attached, %d without a local file.', $updated, $missing);
}
