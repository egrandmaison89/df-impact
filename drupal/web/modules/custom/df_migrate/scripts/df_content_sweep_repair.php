<?php

/**
 * @file
 * Re-run ArticleBodyCleaner on text fields (links, embeds, shortcodes).
 *
 * Usage: drush php:script web/modules/custom/df_migrate/scripts/df_content_sweep_repair.php
 */

use Drupal\node\Entity\Node;
use Drupal\df_setup\ArticleBodyCleaner;

/** @var \Drupal\df_setup\ArticleBodyCleaner $cleaner */
$cleaner = \Drupal::service('df_setup.article_body_cleaner');

$fieldMap = [
  ['bundle' => 'article', 'field' => 'body', 'format' => 'full_html', 'featured' => TRUE],
  ['bundle' => 'in_brief', 'field' => 'field_summary', 'format' => 'full_html', 'featured' => FALSE],
  ['bundle' => 'page', 'field' => 'body', 'format' => 'full_html', 'featured' => FALSE],
  ['bundle' => 'issue', 'field' => 'field_description', 'format' => 'full_html', 'featured' => FALSE],
  ['bundle' => 'issue', 'field' => 'field_leadership_message', 'format' => 'full_html', 'featured' => FALSE],
  ['bundle' => 'issue', 'field' => 'field_issue_promo_2', 'format' => 'full_html', 'featured' => FALSE],
];

$updated = 0;
$scanned = 0;

foreach ($fieldMap as $map) {
  $nids = \Drupal::entityQuery('node')
    ->condition('type', $map['bundle'])
    ->accessCheck(FALSE)
    ->execute();
  foreach (Node::loadMultiple($nids) as $node) {
    if (!$node->hasField($map['field']) || $node->get($map['field'])->isEmpty()) {
      continue;
    }
    $scanned++;
    $original = (string) $node->get($map['field'])->value;
    $format = (string) ($node->get($map['field'])->format ?? $map['format']);
    $featuredUrl = !empty($map['featured']) ? $cleaner->getFeaturedAbsoluteUrl($node) : NULL;
    $result = $cleaner->cleanArticleHtml($original, $featuredUrl);
    $normalized = $result['html'];
    if (trim($normalized) === trim($original)) {
      continue;
    }
    $node->set($map['field'], ['value' => $normalized, 'format' => $format]);
    $node->save();
    $updated++;
  }
}

echo json_encode([
  'generated_at' => date('c'),
  'nodes_scanned_fields' => $scanned,
  'nodes_updated' => $updated,
], JSON_PRETTY_PRINT) . "\n";
