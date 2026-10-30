<?php

/**
 * @file
 * Delete nodes created by editor Playwright E2E and Drush workflow tests.
 *
 * Usage: drush scr ../parity/scripts/cleanup-editor-e2e-nodes.php -- <title-prefix>
 */

declare(strict_types=1);

use Drupal\node\Entity\Node;

$prefix = $extra[0] ?? 'Playwright Winter';

$storage = \Drupal::entityTypeManager()->getStorage('node');
$nids = $storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('title', $prefix, 'STARTS_WITH')
  ->execute();

foreach ($nids as $nid) {
  Node::load($nid)?->delete();
}

$deleted = count($nids);
if (function_exists('df_editor_cleanup_automation_test_nodes')) {
  $deleted += df_editor_cleanup_automation_test_nodes();
}

print "{$deleted} automation test node(s) deleted (prefix {$prefix}).\n";
