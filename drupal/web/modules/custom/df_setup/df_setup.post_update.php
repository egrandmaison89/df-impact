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
 * Repairs "Mismatched entity and/or field definitions" for node field storages.
 *
 * Covers node.body, node.field_homepage_sort_weight and node.field_postx_sort_weight
 * (plus field_issue_banner_is_light, which shows the same drift on long-lived DBs).
 */
function df_setup_post_update_repair_node_field_storage_definitions(): string {
  return df_setup_repair_node_field_storage_definitions([
    'body',
    'field_homepage_sort_weight',
    'field_postx_sort_weight',
    'field_issue_banner_is_light',
  ]);
}

/**
 * Reconciles dedicated field tables and installed schema records (idempotent).
 *
 * The drift seen on migrated DBs is a missing or stale
 * entity.storage_schema.sql record while the field tables already hold data,
 * so tables are created/extended non-destructively and the records rewritten.
 *
 * @param list<string> $field_names
 */
function df_setup_repair_node_field_storage_definitions(array $field_names): string {
  $entity_type_id = 'node';
  $update_manager = \Drupal::entityDefinitionUpdateManager();
  $change_list = $update_manager->getChangeList()[$entity_type_id]['field_storage_definitions'] ?? [];
  $current = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions($entity_type_id);
  $repository = \Drupal::service('entity.last_installed_schema.repository');
  $schema_kv = \Drupal::keyValue('entity.storage_schema.sql');
  $db_schema = \Drupal::database()->schema();
  $storage = \Drupal::entityTypeManager()->getStorage($entity_type_id);
  $storage_schema = (fn () => $this->getStorageSchema())->call($storage);

  $repaired = [];
  foreach ($field_names as $field_name) {
    $definition = $current[$field_name] ?? NULL;
    if ($definition === NULL || !isset($change_list[$field_name])) {
      continue;
    }

    if ($change_list[$field_name] === $update_manager::DEFINITION_CREATED && !$db_schema->tableExists($storage->getTableMapping()->getDedicatedDataTableName($definition))) {
      $update_manager->installFieldStorageDefinition($field_name, $entity_type_id, $definition->getProvider(), $definition);
      $repaired[] = $field_name . ' (installed)';
      continue;
    }

    $schema = (fn ($d) => $this->getSchemaFromStorageDefinition($d))->call($storage_schema, $definition);
    foreach ($schema as $table => $table_schema) {
      if (!$db_schema->tableExists($table)) {
        $db_schema->createTable($table, $table_schema);
        continue;
      }
      foreach ($table_schema['fields'] as $column => $column_schema) {
        if (!$db_schema->fieldExists($table, $column)) {
          $db_schema->addField($table, $column, $column_schema);
        }
      }
      foreach ($table_schema['indexes'] ?? [] as $index => $index_fields) {
        if (!$db_schema->indexExists($table, $index)) {
          $db_schema->addIndex($table, $index, $index_fields, $table_schema);
        }
      }
    }
    $schema_kv->set($entity_type_id . '.field_schema_data.' . $field_name, $schema);
    $repository->setLastInstalledFieldStorageDefinition($definition);
    $repaired[] = $field_name;
  }

  \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
  return $repaired === []
    ? 'Node field storage definitions already in sync.'
    : 'Repaired node field storage definitions: ' . implode(', ', $repaired) . '.';
}
