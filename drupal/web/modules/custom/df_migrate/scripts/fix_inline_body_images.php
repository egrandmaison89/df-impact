<?php

/**
 * @file
 * One-off: localize inline WordPress images in article body and In Brief summary.
 *
 * Usage (from repo drupal/ with DDEV):
 *   ddev drush php:script /var/www/html/web/modules/custom/df_migrate/scripts/fix_inline_body_images.php
 *
 * Or from host:
 *   ddev drush php:script web/modules/custom/df_migrate/scripts/fix_inline_body_images.php
 */

use Drupal\df_migrate\InlineImageHtmlProcessor;

$storage = \Drupal::entityTypeManager()->getStorage('node');

$nids = $storage->getQuery()
  ->condition('type', ['article', 'in_brief'], 'IN')
  ->accessCheck(FALSE)
  ->execute();

$nids = array_values($nids);
$total = count($nids);
echo "Processing $total nodes (article + in_brief)...\n";

$updated = 0;
$skipped = 0;
$errors = 0;
$batch = 25;

foreach (array_chunk($nids, $batch) as $chunk) {
  $nodes = $storage->loadMultiple($chunk);
  foreach ($nodes as $node) {
    try {
      if ($node->bundle() === 'article' && $node->hasField('body') && !$node->get('body')->isEmpty()) {
        $field = $node->get('body');
        $format = $field->format;
        $original = $field->value;
        if ($original === '') {
          $skipped++;
          continue;
        }
        // Process any body that may still reference WP, lazy placeholders, or
        // root-relative /sites/default/files/wp-images paths (upgrade to absolute).
        $localized = InlineImageHtmlProcessor::localize($original);
        if ($localized === $original) {
          $skipped++;
          continue;
        }
        $node->set('body', ['value' => $localized, 'format' => $format]);
        $node->save();
        $updated++;
      }
      elseif ($node->bundle() === 'in_brief' && $node->hasField('field_summary') && !$node->get('field_summary')->isEmpty()) {
        $field = $node->get('field_summary');
        $format = $field->format;
        $original = $field->value;
        if ($original === '') {
          $skipped++;
          continue;
        }
        $localized = InlineImageHtmlProcessor::localize($original);
        if ($localized === $original) {
          $skipped++;
          continue;
        }
        $node->set('field_summary', ['value' => $localized, 'format' => $format]);
        $node->save();
        $updated++;
      }
      else {
        $skipped++;
      }
    }
    catch (\Throwable $e) {
      $errors++;
      echo 'Error nid ' . $node->id() . ': ' . $e->getMessage() . "\n";
    }
  }
  echo "Progress: updated=$updated skipped=$skipped errors=$errors\n";
}

echo "Done. Updated: $updated, skipped (no change or no WP URLs): $skipped, errors: $errors\n";
