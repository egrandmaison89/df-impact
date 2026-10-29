<?php

/**
 * @file
 * CLI wrapper for export publish status audit.
 *
 * Usage (Drupal docroot):
 *   drush scr modules/custom/df_migrate/scripts/df_audit_export_publish_status.php
 *   drush scr modules/custom/df_migrate/scripts/df_audit_export_publish_status.php -- --json
 */

$argv = $_SERVER['argv'] ?? [];
$as_json = in_array('--json', $argv, TRUE);

require_once __DIR__ . '/df_audit_export_publish_status.inc';

try {
  $summary = df_migrate_audit_export_publish_status_run();
}
catch (\Throwable $e) {
  fwrite(STDERR, $e->getMessage() . "\n");
  exit(1);
}

if ($as_json) {
  echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
  exit(0);
}

$results = $summary['migrations'];
echo "Export publish status audit (field: status → published|draft)\n\n";
foreach ($results as $r) {
  echo "{$r['migration']}: matched {$r['matched']}\n";
  echo "  export published / Drupal unpublished: " . count($r['export_published_drupal_unpublished']) . "\n";
  echo "  export draft / Drupal published: " . count($r['export_draft_drupal_published']) . "\n";
}
echo "\nTotals: matched {$summary['matched_total']}, mismatches pub→unpub {$summary['export_published_drupal_unpublished_total']}, mismatches draft→pub {$summary['export_draft_drupal_published_total']}\n";

if ($summary['export_published_drupal_unpublished_total'] > 0 || $summary['export_draft_drupal_published_total'] > 0) {
  foreach ($results as $r) {
    foreach (array_merge($r['export_published_drupal_unpublished'], $r['export_draft_drupal_published']) as $row) {
      echo "  wp_id={$row['wp_id']} nid={$row['nid']} export={$row['export_status']} drupal={$row['drupal_status']} {$row['alias']}\n";
    }
  }
  exit(2);
}

exit(0);
