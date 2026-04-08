<?php

/**
 * @file
 * Creates image styles for Dana-Farber Impact Magazine.
 *
 * Run with: ddev drush scr scripts/create-image-styles.php
 */

use Drupal\image\Entity\ImageStyle;

$styles = [
  'hero_banner' => [
    'label' => 'Hero Banner (2560x981)',
    'effects' => [
      ['id' => 'focal_point_scale_and_crop', 'data' => ['width' => 2560, 'height' => 981]],
    ],
  ],
  'issue_banner' => [
    'label' => 'Issue Banner (1750x677)',
    'effects' => [
      ['id' => 'focal_point_scale_and_crop', 'data' => ['width' => 1750, 'height' => 677]],
    ],
  ],
  'issue_cover' => [
    'label' => 'Issue Cover (750x970)',
    'effects' => [
      ['id' => 'focal_point_scale_and_crop', 'data' => ['width' => 750, 'height' => 970]],
    ],
  ],
  'article_featured' => [
    'label' => 'Article Featured (1450x1040)',
    'effects' => [
      ['id' => 'focal_point_scale_and_crop', 'data' => ['width' => 1450, 'height' => 1040]],
    ],
  ],
  'card_large' => [
    'label' => 'Card Large (625x400)',
    'effects' => [
      ['id' => 'focal_point_scale_and_crop', 'data' => ['width' => 625, 'height' => 400]],
    ],
  ],
  'card_medium' => [
    'label' => 'Card Medium (400x267)',
    'effects' => [
      ['id' => 'focal_point_scale_and_crop', 'data' => ['width' => 400, 'height' => 267]],
    ],
  ],
  'card_small' => [
    'label' => 'Card Small (350x200)',
    'effects' => [
      ['id' => 'focal_point_scale_and_crop', 'data' => ['width' => 350, 'height' => 200]],
    ],
  ],
  'in_brief' => [
    'label' => 'In Brief (300x200)',
    'effects' => [
      ['id' => 'focal_point_scale_and_crop', 'data' => ['width' => 300, 'height' => 200]],
    ],
  ],
];

foreach ($styles as $name => $config) {
  $style = ImageStyle::load($name);
  if (!$style) {
    $style = ImageStyle::create([
      'name' => $name,
      'label' => $config['label'],
    ]);
    $style->save();

    foreach ($config['effects'] as $effect) {
      $style->addImageEffect($effect);
    }
    $style->save();
    echo "Created image style: {$config['label']}\n";
  }
  else {
    echo "Image style exists: {$config['label']}\n";
  }
}

echo "\nAll image styles created successfully.\n";
