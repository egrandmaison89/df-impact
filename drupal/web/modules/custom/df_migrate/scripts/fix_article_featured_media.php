<?php

/**
 * @file
 * Repoints article field_featured_image from file IDs to image media entities.
 *
 * Run after fixing DownloadImage / view displays, without a full migration rollback.
 *
 * Usage (DDEV, from drupal/):
 *   ddev drush php:script web/modules/custom/df_migrate/scripts/fix_article_featured_media.php
 */

use Drupal\file\FileInterface;
use Drupal\media\Entity\Media;

$node_storage = \Drupal::entityTypeManager()->getStorage('node');
$media_storage = \Drupal::entityTypeManager()->getStorage('media');
$file_storage = \Drupal::entityTypeManager()->getStorage('file');

$nids = $node_storage->getQuery()
  ->condition('type', 'article')
  ->exists('field_featured_image')
  ->accessCheck(FALSE)
  ->execute();

$nids = array_values($nids);
$total = count($nids);
echo "Checking $total articles with a featured image reference...\n";

$skipped_ok = 0;
$updated = 0;
$skipped_none = 0;

foreach (array_chunk($nids, 50) as $chunk) {
  foreach ($node_storage->loadMultiple($chunk) as $node) {
    if (!$node->hasField('field_featured_image') || $node->get('field_featured_image')->isEmpty()) {
      $skipped_none++;
      continue;
    }

    $target_id = (int) $node->get('field_featured_image')->target_id;
    if ($target_id < 1) {
      $skipped_none++;
      continue;
    }

    $media = $media_storage->load($target_id);
    if ($media && $media->bundle() === 'image') {
      $skipped_ok++;
      continue;
    }

    $file = $file_storage->load($target_id);
    if (!$file instanceof FileInterface) {
      echo 'WARN nid ' . $node->id() . ": target_id $target_id is not an image media or a file; leave unchanged.\n";
      continue;
    }

    $existing = \Drupal::entityQuery('media')
      ->condition('bundle', 'image')
      ->condition('field_media_image.target_id', $file->id())
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();

    if (!empty($existing)) {
      $mid = (int) reset($existing);
    }
    else {
      $title = $node->getTitle();
      $alt = is_string($title) && $title !== ''
        ? mb_substr($title, 0, 512)
        : '';
      if ($alt === '') {
        $basename = pathinfo($file->getFilename(), PATHINFO_FILENAME);
        $alt = ucfirst(trim(str_replace(['-', '_'], ' ', $basename))) ?: 'Image';
      }

      $new_media = Media::create([
        'bundle' => 'image',
        'uid' => $node->getOwnerId() ?: 1,
        'name' => $file->getFilename(),
        'field_media_image' => [
          'target_id' => $file->id(),
          'alt' => $alt,
        ],
        'status' => 1,
      ]);
      $new_media->save();
      $mid = (int) $new_media->id();
    }

    $node->set('field_featured_image', ['target_id' => $mid]);
    $node->save();
    $updated++;
    echo 'Updated nid ' . $node->id() . ": file {$file->id()} -> media $mid\n";
  }
}

echo "\nDone. Already correct (media): $skipped_ok; updated: $updated; empty: $skipped_none\n";
