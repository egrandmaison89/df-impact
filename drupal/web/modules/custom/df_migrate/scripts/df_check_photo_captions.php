<?php

/**
 * @file
 * Reports photo caption field coverage on articles.
 */

use Drupal\node\Entity\Node;

$nids = \Drupal::entityQuery('node')
  ->condition('type', 'article')
  ->accessCheck(FALSE)
  ->execute();

$with_img = 0;
$empty_caption = 0;
$has_caption = 0;

foreach (Node::loadMultiple($nids) as $node) {
  if (!$node->hasField('field_featured_image') || $node->get('field_featured_image')->isEmpty()) {
    continue;
  }
  $with_img++;
  if ($node->hasField('field_photo_caption') && !$node->get('field_photo_caption')->isEmpty()) {
    $has_caption++;
  }
  else {
    $empty_caption++;
  }
}

echo "Articles with featured image: $with_img\n";
echo "With photo caption: $has_caption\n";
echo "Missing photo caption: $empty_caption\n";
