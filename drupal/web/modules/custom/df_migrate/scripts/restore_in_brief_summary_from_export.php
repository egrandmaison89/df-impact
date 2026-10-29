<?php

/**
 * @file
 * Restores in_brief field_summary HTML from in_brief.json body export.
 */

declare(strict_types=1);

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Restores in_brief field_summary from export JSON body HTML.
 */
function df_migrate_restore_in_brief_summary_from_export(): array {
  $file = DRUPAL_ROOT . '/modules/custom/df_migrate/data/in_brief.json';
  if (!is_readable($file)) {
    return ['updated' => 0, 'error' => 'missing json'];
  }
  $rows = json_decode((string) file_get_contents($file), TRUE);
  if (!is_array($rows)) {
    return ['updated' => 0, 'error' => 'invalid json'];
  }
  $alias_manager = \Drupal::service('path_alias.manager');
  $updated = 0;
  foreach ($rows as $row) {
    if (!is_array($row)) {
      continue;
    }
    $body = (string) ($row['body'] ?? '');
    if ($body === '') {
      continue;
    }
    $alias = $row['drupal_alias'] ?? NULL;
    if (!$alias) {
      $wp = preg_replace('#^https?://[^/]+#', '', (string) ($row['wp_url'] ?? ''));
      $alias = $wp;
    }
    if (!is_string($alias) || $alias === '') {
      continue;
    }
    $path = '/' . ltrim($alias, '/');
    $internal = $alias_manager->getPathByAlias($path);
    if (!preg_match('/^\/node\/(\d+)$/', $internal, $m)) {
      continue;
    }
    $node = Node::load((int) $m[1]);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'in_brief' || !$node->hasField('field_summary')) {
      continue;
    }
    if ((string) $node->get('field_summary')->value === $body) {
      continue;
    }
    $node->set('field_summary', [
      'value' => $body,
      'format' => 'full_html',
    ]);
    $node->save();
    $updated++;
  }
  return ['updated' => $updated];
}
