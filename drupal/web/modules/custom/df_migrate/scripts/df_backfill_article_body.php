<?php

/**
 * @file
 * Backfills article body HTML (removes WP decks, duplicate hero figures, links).
 *
 * Usage (DDEV, from drupal/):
 *   ddev drush php:script web/modules/custom/df_migrate/scripts/df_backfill_article_body.php
 */

use Drupal\node\Entity\Node;

/** @var \Drupal\df_setup\ArticleBodyCleaner $cleaner */
$cleaner = \Drupal::service('df_setup.article_body_cleaner');

$nids = \Drupal::entityQuery('node')
  ->condition('type', 'article')
  ->accessCheck(FALSE)
  ->execute();

if (!$nids) {
  echo "No article nodes found.\n";
  return;
}

$updated = 0;
foreach (Node::loadMultiple($nids) as $node) {
  if (!$node->hasField('body') || $node->get('body')->isEmpty()) {
    continue;
  }

  $original = $node->get('body')->value ?? '';
  $format = $node->get('body')->format ?? 'full_html';
  $featuredUrl = $cleaner->getFeaturedAbsoluteUrl($node);
  $result = $cleaner->cleanArticleHtml($original, $featuredUrl);

  $htmlChanged = trim($result['html']) !== trim($original);
  $bylineFill = $result['byline'] !== '' && $node->hasField('field_byline') && $node->get('field_byline')->isEmpty();
  $creditFill = $result['photo_credit'] !== '' && $node->hasField('field_photo_credit') && $node->get('field_photo_credit')->isEmpty();
  if (!$htmlChanged && !$bylineFill && !$creditFill) {
    continue;
  }

  if ($htmlChanged) {
    $node->set('body', ['value' => $result['html'], 'format' => $format]);
  }
  if ($bylineFill) {
    $node->set('field_byline', $result['byline']);
  }
  if ($creditFill) {
    $node->set('field_photo_credit', $result['photo_credit']);
  }

  $node->save();
  $updated++;
}

echo "Article bodies updated: $updated / " . count($nids) . "\n";
