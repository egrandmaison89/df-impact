#!/usr/bin/env php
<?php
/**
 * Bulk publish all article and in_brief nodes that came from migration.
 * Sets moderation_state to 'published' and status to 1.
 */

$published_articles = 0;
$published_inbrief = 0;
$batch_size = 50;

$storage = \Drupal::entityTypeManager()->getStorage('node');

// Publish articles
$nids = \Drupal::entityQuery('node')
  ->condition('type', 'article')
  ->condition('status', 0)
  ->accessCheck(FALSE)
  ->execute();

echo "Found " . count($nids) . " unpublished articles\n";

$chunks = array_chunk($nids, $batch_size);
foreach ($chunks as $chunk) {
  $nodes = $storage->loadMultiple($chunk);
  foreach ($nodes as $node) {
    try {
      $node->set('status', 1);
      if ($node->hasField('moderation_state')) {
        $node->set('moderation_state', 'published');
      }
      $node->save();
      $published_articles++;
    } catch (\Exception $e) {
      echo "Error on node " . $node->id() . ": " . $e->getMessage() . "\n";
    }
  }
  echo "Published $published_articles articles so far...\n";
}

// Publish in_brief
$nids2 = \Drupal::entityQuery('node')
  ->condition('type', 'in_brief')
  ->condition('status', 0)
  ->accessCheck(FALSE)
  ->execute();

echo "\nFound " . count($nids2) . " unpublished in_brief items\n";

$chunks2 = array_chunk($nids2, $batch_size);
foreach ($chunks2 as $chunk) {
  $nodes = $storage->loadMultiple($chunk);
  foreach ($nodes as $node) {
    try {
      $node->set('status', 1);
      if ($node->hasField('moderation_state')) {
        $node->set('moderation_state', 'published');
      }
      $node->save();
      $published_inbrief++;
    } catch (\Exception $e) {
      echo "Error on node " . $node->id() . ": " . $e->getMessage() . "\n";
    }
  }
  echo "Published $published_inbrief in_brief items so far...\n";
}

echo "\n=== DONE ===\n";
echo "Articles published: $published_articles\n";
echo "In Brief published: $published_inbrief\n";
