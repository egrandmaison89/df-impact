<?php

/**
 * @file
 * Count &#NNN;, &amp;#, and &quot; leftovers across common text columns.
 *
 * drush php:script modules/custom/df_migrate/scripts/audit_entity_leftovers.php
 */

$db = \Drupal::database();
$patterns = [
  'numeric' => '&#%',
  'amp_hash' => '&amp;#%',
  'quot' => '&quot;%',
];

$tables = [
  'node_field_data' => ['title'],
  'node__body' => ['body_value'],
  'node__field_excerpt' => ['field_excerpt_value'],
  'node__field_summary' => ['field_summary_value'],
  'node__field_meta_description' => ['field_meta_description_value'],
  'media_field_data' => ['name'],
  'media__field_media_image' => ['field_media_image_alt'],
  'taxonomy_term_field_data' => ['name'],
  'taxonomy_term__description' => ['description_value'],
  'menu_link_content_data' => ['title'],
];

$report = ['total' => 0, 'by_table' => []];
foreach ($tables as $table => $columns) {
  foreach ($columns as $col) {
    if (!$db->schema()->fieldExists($table, $col)) {
      continue;
    }
    foreach ($patterns as $label => $like) {
      $count = (int) $db->select($table, 't')
        ->condition($col, $like, 'LIKE')
        ->countQuery()
        ->execute()
        ->fetchField();
      if ($count > 0) {
        $report['by_table']["$table.$col.$label"] = $count;
        $report['total'] += $count;
      }
    }
  }
}

echo json_encode($report, JSON_PRETTY_PRINT) . "\n";
exit($report['total'] === 0 ? 0 : 1);
