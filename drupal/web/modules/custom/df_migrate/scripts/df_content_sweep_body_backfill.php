<?php

/**
 * @file
 * Backfill article body from migrate export when stored body is shorter.
 *
 * Usage: drush php:script web/modules/custom/df_migrate/scripts/df_content_sweep_body_backfill.php
 */

/**
 * Idempotent: restore truncated article bodies from articles.json export.
 *
 * @return array{scanned: int, updated: int, error?: string}
 */
function df_migrate_content_sweep_body_backfill(): array {
  /** @var \Drupal\df_setup\ArticleBodyCleaner $cleaner */
  $cleaner = \Drupal::service('df_setup.article_body_cleaner');

  $json_path = DRUPAL_ROOT . '/modules/custom/df_migrate/data/articles.json';
  if (!is_readable($json_path)) {
    return ['scanned' => 0, 'updated' => 0, 'error' => 'articles.json missing'];
  }

  $articles = json_decode((string) file_get_contents($json_path), TRUE);
  if (!is_array($articles)) {
    return ['scanned' => 0, 'updated' => 0, 'error' => 'articles.json invalid'];
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
    $node = \Drupal\node\Entity\Node::load((int) $m[1]);
    if (!$node instanceof \Drupal\node\Entity\Node || $node->bundle() !== 'article' || !$node->hasField('body')) {
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
    $plain_clean = strlen(trim(strip_tags($cleaned['html']));
    if ($plain_clean <= $plain_current + 20) {
      continue;
    }
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

  return ['scanned' => $scanned, 'updated' => $updated];
}

$result = df_migrate_content_sweep_body_backfill();
echo json_encode(['generated_at' => date('c'), ...$result], JSON_PRETTY_PRINT) . "\n";
