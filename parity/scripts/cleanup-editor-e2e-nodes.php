<?php

/**
 * @file
 * Delete draft/published nodes created by editor Playwright E2E (Winter YEAR* titles).
 *
 * Usage: drush scr ../parity/scripts/cleanup-editor-e2e-nodes.php -- <year>
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
  $node = Node::load($nid);
  $node?->delete();
}

$extraTitles = ['DF Test Issue Workflow', 'Debug', 'D', 'DF Test Article 1', 'DF Test Article 2'];
$extraNids = $storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('title', $extraTitles, 'IN')
  ->execute();
foreach ($extraNids as $nid) {
  Node::load($nid)?->delete();
}

print count($nids) + count($extraNids) . " node(s) deleted for title prefix {$prefix}.\n";
