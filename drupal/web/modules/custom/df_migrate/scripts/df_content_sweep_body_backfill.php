<?php

/**
 * @file
 * Backfill article body from migrate export when stored body is shorter.
 *
 * Usage: drush php:script web/modules/custom/df_migrate/scripts/df_content_sweep_body_backfill.php
 */

use Drupal\node\Entity\Node;
use Drupal\df_setup\ArticleBodyCleaner;

/** @var \Drupal\df_setup\ArticleBodyCleaner $cleaner */
$cleaner = \Drupal::service('df_setup.article_body_cleaner');

$json_path = __DIR__ . '/../data/articles.json';
if (!is_readable($json_path)) {
  echo json_encode(['error' => 'articles.json missing']) . "\n";
  return;
}

$articles = json_decode(file_get_contents($json_path), TRUE);
if (!is_array($articles)) {
  echo json_encode(['error' => 'articles.json invalid']) . "\n";
  return;
}

$alias_manager = \Drupal::service('path_alias.manager');
$updated = 0;
$scanned = 0;

foreach ($articles as $row) {
  $alias = $row['drupal_alias'] ?? '';
  $export_body = (string) ($row['body'] ?? '');
  if (!is_string($alias) || $alias === '' || $export_body === '') {
    continue;
  }
  $internal = $alias_manager->getPathByAlias($alias);
  if (!preg_match('/^\/node\/(\d+)$/', $internal, $m)) {
    continue;
  }
  $node = Node::load((int) $m[1]);
  if (!$node instanceof Node || $node->bundle() !== 'article' || !$node->hasField('body')) {
    continue;
  }
  $scanned++;
  $current = (string) $node->get('body')->value;
  $plain_current = strlen(trim(strip_tags($current)));
  $plain_export = strlen(trim(strip_tags($export_body)));
  if ($plain_export <= $plain_current + 80) {
    continue;
  }
  $featured = $cleaner->getFeaturedAbsoluteUrl($node);
  $cleaned = $cleaner->cleanArticleHtml($export_body, $featured);
  $plain_clean = strlen(trim(strip_tags($cleaned['html'])));
  if ($plain_clean <= $plain_current + 40) {
    continue;
  }
  // Skip when cleaner would not increase stored body (export-only blocks removed at render).
  $node->set('body', [
    'value' => $cleaned['html'],
    'format' => $node->get('body')->format ?? 'full_html',
  ]);
  if ($cleaned['byline'] !== '' && $node->hasField('field_byline') && $node->get('field_byline')->isEmpty()) {
    $node->set('field_byline', $cleaned['byline']);
  }
  $node->save();
  $updated++;
}

echo json_encode([
  'generated_at' => date('c'),
  'articles_scanned' => $scanned,
  'nodes_updated' => $updated,
], JSON_PRETTY_PRINT) . "\n";
