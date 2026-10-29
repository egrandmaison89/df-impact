<?php

/**
 * @file
 * Keeps in_brief.json export bodies aligned with field_summary (content-sweep).
 */

/**
 * @return array{updated: int, scanned: int}
 */
function df_migrate_sync_in_brief_export_from_nodes(): array {
  $json_path = DRUPAL_ROOT . '/modules/custom/df_migrate/data/in_brief.json';
  if (!is_readable($json_path)) {
    return ['updated' => 0, 'scanned' => 0];
  }
  $rows = json_decode((string) file_get_contents($json_path), TRUE);
  if (!is_array($rows)) {
    return ['updated' => 0, 'scanned' => 0];
  }
  $by_alias = [];
  foreach ($rows as $index => $row) {
    if (!empty($row['drupal_alias'])) {
      $by_alias[$row['drupal_alias']] = $index;
    }
  }
  $alias_manager = \Drupal::service('path_alias.manager');
  $updated = 0;
  $scanned = 0;
  foreach ($by_alias as $alias => $index) {
    $internal = $alias_manager->getPathByAlias($alias);
    if (!preg_match('/^\/node\/(\d+)$/', $internal, $m)) {
      continue;
    }
    $node = \Drupal\node\Entity\Node::load((int) $m[1]);
    if (!$node instanceof \Drupal\node\Entity\Node || $node->bundle() !== 'in_brief') {
      continue;
    }
    if (!$node->hasField('field_summary') || $node->get('field_summary')->isEmpty()) {
      continue;
    }
    $scanned++;
    $summary = (string) $node->get('field_summary')->value;
    $created = date('c', (int) $node->getCreatedTime());
    if (($rows[$index]['body'] ?? '') === $summary && ($rows[$index]['created'] ?? '') === $created) {
      continue;
    }
    $rows[$index]['body'] = $summary;
    $rows[$index]['field_summary'] = $summary;
    $rows[$index]['created'] = $created;
    $updated++;
  }
  if ($updated > 0) {
    file_put_contents($json_path, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
  }
  return ['updated' => $updated, 'scanned' => $scanned];
}

if (PHP_SAPI === 'cli' && !empty($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
  $result = df_migrate_sync_in_brief_export_from_nodes();
  echo json_encode(['generated_at' => date('c'), ...$result], JSON_PRETTY_PRINT) . "\n";
}
