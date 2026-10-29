<?php

/**
 * @file
 * Seeds field_excerpt from archive_row_wp_excerpts.json (reference-pack listings).
 *
 * Idempotent: updates only when excerpt is empty or already matches the seed.
 */

declare(strict_types=1);

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Backfills archive listing excerpts onto article nodes.
 */
function df_migrate_backfill_archive_row_excerpts(): array {
  $file = DRUPAL_ROOT . '/modules/custom/df_migrate/data/archive_row_wp_excerpts.json';
  if (!is_readable($file)) {
    return ['updated' => 0, 'skipped' => 0, 'missing' => 0, 'error' => 'json missing'];
  }
  try {
    $payload = json_decode((string) file_get_contents($file), TRUE, 512, JSON_THROW_ON_ERROR);
  }
  catch (\JsonException $e) {
    return ['updated' => 0, 'skipped' => 0, 'missing' => 0, 'error' => $e->getMessage()];
  }
  $excerpts = $payload['excerpts'] ?? [];
  if (!is_array($excerpts)) {
    return ['updated' => 0, 'skipped' => 0, 'missing' => 0, 'error' => 'invalid excerpts'];
  }

  $alias_manager = \Drupal::service('path_alias.manager');
  $articles_file = DRUPAL_ROOT . '/modules/custom/df_migrate/data/articles.json';
  $drupal_alias_by_wp_path = [];
  if (is_readable($articles_file)) {
    $articles = json_decode((string) file_get_contents($articles_file), TRUE);
    if (is_array($articles)) {
      foreach ($articles as $row) {
        if (!is_array($row) || empty($row['drupal_alias']) || empty($row['wp_url'])) {
          continue;
        }
        $wp_path = preg_replace('#^https?://[^/]+#', '', (string) $row['wp_url']) ?? '';
        $wp_path = rtrim($wp_path, '/') ?: '/';
        $drupal_alias_by_wp_path[$wp_path] = '/' . ltrim((string) $row['drupal_alias'], '/');
      }
    }
  }
  $updated = 0;
  $skipped = 0;
  $missing = 0;

  foreach ($excerpts as $alias => $expected) {
    if (!is_string($alias) || !is_string($expected) || trim($expected) === '') {
      continue;
    }
    $path = '/' . ltrim($alias, '/');
    $internal = $alias_manager->getPathByAlias($path);
    if (!preg_match('/^\/node\/(\d+)$/', $internal, $m)) {
      $fallback = $drupal_alias_by_wp_path[$path] ?? NULL;
      if (is_string($fallback)) {
        $internal = $alias_manager->getPathByAlias($fallback);
      }
    }
    if (!preg_match('/^\/node\/(\d+)$/', $internal, $m)) {
      $missing++;
      continue;
    }
    $node = Node::load((int) $m[1]);
    if (!$node instanceof NodeInterface) {
      $missing++;
      continue;
    }
    if ($node->bundle() === 'article' && $node->hasField('field_excerpt')) {
      $field_name = 'field_excerpt';
    }
    elseif ($node->bundle() === 'in_brief' && $node->hasField('field_excerpt')) {
      $field_name = 'field_excerpt';
    }
    else {
      $missing++;
      continue;
    }
    $expected_norm = preg_replace('/\s+/u', ' ', trim($expected)) ?? trim($expected);
    $current = '';
    if (!$node->get($field_name)->isEmpty()) {
      $current = preg_replace('/\s+/u', ' ', trim(strip_tags((string) $node->get($field_name)->value))) ?? '';
    }
    if ($current === $expected_norm) {
      $skipped++;
      continue;
    }
    $node->set($field_name, [
      'value' => $expected_norm,
      'format' => 'plain_text',
    ]);
    $node->save();
    $updated++;
  }

  return [
    'updated' => $updated,
    'skipped' => $skipped,
    'missing' => $missing,
  ];
}
