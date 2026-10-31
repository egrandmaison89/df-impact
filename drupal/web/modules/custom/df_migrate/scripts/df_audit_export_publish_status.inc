<?php

/**
 * @file
 * Compare export JSON `status` (published|draft) to Drupal node status.
 */

use Drupal\node\NodeInterface;

/**
 * @return array{
 *   migration: string,
 *   map_table: string,
 *   json_file: string,
 *   matched: int,
 *   export_published_drupal_unpublished: list<array<string, mixed>>,
 *   export_draft_drupal_published: list<array<string, mixed>>,
 *   unmapped_export_rows: int,
 *   unmapped_drupal_nodes: int,
 *   bundle: string,
 * }
 */
function df_audit_export_publish_status_for(string $migration_id, string $map_table, string $json_relative): array {
  $path = \Drupal::root() . '/' . $json_relative;
  $rows = is_readable($path) ? json_decode((string) file_get_contents($path), TRUE) : NULL;
  if (!is_array($rows)) {
    throw new \RuntimeException("Unreadable or invalid JSON: $json_relative");
  }

  $database = \Drupal::database();
  if (!$database->schema()->tableExists($map_table)) {
    throw new \RuntimeException("Missing migrate map table: $map_table");
  }

  $wp_to_nid = $database->select($map_table, 'm')
    ->fields('m', ['sourceid1', 'destid1'])
    ->execute()
    ->fetchAllKeyed();
  $wp_to_nid = array_map('intval', $wp_to_nid);

  $node_storage = \Drupal::entityTypeManager()->getStorage('node');
  $matched = 0;
  $pub_dr_unpub = [];
  $draft_dr_pub = [];
  $seen_nids = [];

  foreach ($rows as $row) {
    if (!is_array($row)) {
      continue;
    }
    $wp_id = isset($row['wp_id']) ? (int) $row['wp_id'] : 0;
    if ($wp_id <= 0) {
      continue;
    }
    $export_status = isset($row['status']) ? (string) $row['status'] : '';
    if ($export_status !== 'published' && $export_status !== 'draft') {
      continue;
    }
    if (!isset($wp_to_nid[$wp_id])) {
      continue;
    }
    $nid = (int) $wp_to_nid[$wp_id];
    $seen_nids[$nid] = TRUE;
    $node = $node_storage->load($nid);
    if (!$node instanceof NodeInterface) {
      continue;
    }
    $matched++;
    $drupal_published = (bool) $node->isPublished();
    $export_published = $export_status === 'published';
    $entry = [
      'wp_id' => $wp_id,
      'nid' => $nid,
      'export_status' => $export_status,
      'drupal_status' => $drupal_published ? 1 : 0,
      'alias' => $node->hasField('path') ? (string) $node->get('path')->alias : '',
      'title' => $node->label(),
    ];
    if ($export_published && !$drupal_published) {
      $pub_dr_unpub[] = $entry;
    }
    elseif (!$export_published && $drupal_published) {
      $draft_dr_pub[] = $entry;
    }
  }

  $bundle = $migration_id === 'df_in_brief' ? 'in_brief' : 'article';
  $all_mapped_nids = array_values(array_unique(array_map('intval', $wp_to_nid)));
  $unmapped_drupal = 0;
  foreach ($all_mapped_nids as $nid) {
    if (!isset($seen_nids[$nid])) {
      $unmapped_drupal++;
    }
  }

  return [
    'migration' => $migration_id,
    'map_table' => $map_table,
    'json_file' => $json_relative,
    'matched' => $matched,
    'export_published_drupal_unpublished' => $pub_dr_unpub,
    'export_draft_drupal_published' => $draft_dr_pub,
    'unmapped_export_rows' => max(0, count($rows) - $matched),
    'unmapped_drupal_nodes' => $unmapped_drupal,
    'bundle' => $bundle,
  ];
}

/**
 * Runs publish-status audit for articles and in_brief exports.
 *
 * @return array<string, mixed>
 */
function df_migrate_audit_export_publish_status_run(): array {
  $results = [
    df_audit_export_publish_status_for(
      'df_articles',
      'migrate_map_df_articles',
      'modules/custom/df_migrate/data/articles.json',
    ),
    df_audit_export_publish_status_for(
      'df_in_brief',
      'migrate_map_df_in_brief',
      'modules/custom/df_migrate/data/in_brief.json',
    ),
  ];

  return [
    'matched_total' => array_sum(array_column($results, 'matched')),
    'export_published_drupal_unpublished_total' => array_sum(array_map(
      static fn(array $r): int => count($r['export_published_drupal_unpublished']),
      $results,
    )),
    'export_draft_drupal_published_total' => array_sum(array_map(
      static fn(array $r): int => count($r['export_draft_drupal_published']),
      $results,
    )),
    'migrations' => $results,
  ];
}
