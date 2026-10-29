<?php

/**
 * @file
 * Backfill field_issue_banner_is_light from WP reference-pack hero markup.
 *
 * Usage: drush scr web/modules/custom/df_migrate/scripts/backfill_issue_banner_is_light.php
 */

declare(strict_types=1);

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * @return array<string, bool>
 */
function df_migrate_wp_issue_banner_is_light_map(): array {
  $repo_root = dirname(DRUPAL_ROOT, 2);
  $pack_root = $repo_root . '/parity/reference-pack';
  if (!is_dir($pack_root)) {
    return [];
  }
  $map = [];
  foreach (scandir($pack_root) ?: [] as $dir) {
    if ($dir === '.' || $dir === '..') {
      continue;
    }
    if (!str_ends_with($dir, '-issue')) {
      continue;
    }
    $html_file = $pack_root . '/' . $dir . '/tablet/rendered.html';
    if (!is_readable($html_file)) {
      continue;
    }
    $html = file_get_contents($html_file);
    if ($html === FALSE) {
      continue;
    }
    $is_light = (bool) preg_match(
      '/<div class="wp-block-cover alignfull is-light"/',
      $html,
    );
    $map['/' . $dir] = $is_light;
  }
  return $map;
}

$map = df_migrate_wp_issue_banner_is_light_map();
if ($map === []) {
  throw new \RuntimeException('No reference-pack issue paths found for backfill.');
}

$storage = \Drupal::entityTypeManager()->getStorage('node');
$alias_manager = \Drupal::service('path_alias.manager');
$updated = 0;

$query = $storage->getQuery()->condition('type', 'issue')->accessCheck(FALSE);
foreach ($storage->loadMultiple($query->execute()) as $node) {
  if (!$node instanceof NodeInterface) {
    continue;
  }
  if (!$node->hasField('field_issue_banner_is_light')) {
    throw new \RuntimeException('Install field_issue_banner_is_light before backfill.');
  }
  $path = $alias_manager->getAliasByPath('/node/' . $node->id());
  if (!isset($map[$path])) {
    continue;
  }
  $want = $map[$path] ? 1 : 0;
  $have = $node->get('field_issue_banner_is_light')->isEmpty()
    ? NULL
    : (int) $node->get('field_issue_banner_is_light')->value;
  if ($have === $want) {
    continue;
  }
  $node->set('field_issue_banner_is_light', $want);
  $node->save();
  $updated++;
  print "Updated nid {$node->id()} ($path) field_issue_banner_is_light=$want\n";
}

print "Backfill complete: $updated issue(s) updated.\n";
