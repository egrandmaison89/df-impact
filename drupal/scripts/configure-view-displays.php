#!/usr/bin/env php
<?php

/**
 * @file
 * Configure entity view displays for all content types.
 * Ensures fields are visible with appropriate formatters.
 */

use Drupal\Core\Entity\Entity\EntityViewDisplay;

// --- ARTICLE: Default (Full) View Display ---
$article_display = EntityViewDisplay::load('node.article.default');
if ($article_display) {
  // Featured image: entity reference to media (not a core image field).
  $article_display->setComponent('field_featured_image', [
    'type' => 'entity_reference_entity_view',
    'label' => 'hidden',
    'settings' => [
      'view_mode' => 'default',
      'link' => FALSE,
    ],
    'weight' => -10,
    'region' => 'content',
  ]);

  // Subtitle
  $article_display->setComponent('field_subtitle', [
    'type' => 'string',
    'label' => 'hidden',
    'settings' => ['link_to_entity' => false],
    'weight' => -8,
    'region' => 'content',
  ]);

  // Byline
  $article_display->setComponent('field_byline', [
    'type' => 'string',
    'label' => 'hidden',
    'settings' => ['link_to_entity' => false],
    'weight' => -7,
    'region' => 'content',
  ]);

  // Photo credit
  $article_display->setComponent('field_photo_credit', [
    'type' => 'string',
    'label' => 'hidden',
    'settings' => ['link_to_entity' => false],
    'weight' => -6,
    'region' => 'content',
  ]);

  // Issue reference
  $article_display->setComponent('field_issue', [
    'type' => 'entity_reference_label',
    'label' => 'hidden',
    'settings' => ['link' => true],
    'weight' => -5,
    'region' => 'content',
  ]);

  // Topics
  $article_display->setComponent('field_topics', [
    'type' => 'entity_reference_label',
    'label' => 'hidden',
    'settings' => ['link' => true],
    'weight' => 20,
    'region' => 'content',
  ]);

  // Cancer types
  $article_display->setComponent('field_cancer_types', [
    'type' => 'entity_reference_label',
    'label' => 'hidden',
    'settings' => ['link' => true],
    'weight' => 21,
    'region' => 'content',
  ]);

  // Content sections (paragraphs)
  $article_display->setComponent('field_content_sections', [
    'type' => 'entity_reference_revisions_entity_view',
    'label' => 'hidden',
    'settings' => ['view_mode' => 'default', 'link' => ''],
    'weight' => 5,
    'region' => 'content',
  ]);

  // Related articles
  $article_display->setComponent('field_related_articles', [
    'type' => 'entity_reference_entity_view',
    'label' => 'hidden',
    'settings' => ['view_mode' => 'teaser', 'link' => false],
    'weight' => 30,
    'region' => 'content',
  ]);

  // Excerpt - visible for reference but typically used in teasers
  $article_display->setComponent('field_excerpt', [
    'type' => 'text_default',
    'label' => 'hidden',
    'settings' => [],
    'weight' => -3,
    'region' => 'content',
  ]);

  // Hide administrative/meta fields that shouldn't render on full view
  $article_display->removeComponent('field_homepage_placement');
  $article_display->removeComponent('field_meta_description');
  $article_display->removeComponent('field_content_type');
  $article_display->removeComponent('comment');
  $article_display->removeComponent('links');
  $article_display->removeComponent('field_image'); // use field_featured_image instead
  $article_display->removeComponent('field_tags'); // use field_topics instead

  $article_display->save();
  echo "✅ Article default view display configured.\n";
}

// --- ARTICLE: Teaser View Display ---
// Create teaser view display for article cards
$teaser_display = EntityViewDisplay::load('node.article.teaser');
if (!$teaser_display) {
  $teaser_display = EntityViewDisplay::create([
    'targetEntityType' => 'node',
    'bundle' => 'article',
    'mode' => 'teaser',
    'status' => true,
  ]);
}

$teaser_display->setComponent('field_featured_image', [
  'type' => 'entity_reference_entity_view',
  'label' => 'hidden',
  'settings' => [
    'view_mode' => 'default',
    'link' => FALSE,
  ],
  'weight' => -10,
  'region' => 'content',
]);

$teaser_display->setComponent('field_excerpt', [
  'type' => 'text_default',
  'label' => 'hidden',
  'settings' => [],
  'weight' => 0,
  'region' => 'content',
]);

$teaser_display->setComponent('field_topics', [
  'type' => 'entity_reference_label',
  'label' => 'hidden',
  'settings' => ['link' => true],
  'weight' => 5,
  'region' => 'content',
]);

// Hide everything else in teaser
$teaser_display->removeComponent('body');
$teaser_display->removeComponent('comment');
$teaser_display->removeComponent('links');
$teaser_display->removeComponent('field_image');
$teaser_display->removeComponent('field_tags');
$teaser_display->removeComponent('field_subtitle');
$teaser_display->removeComponent('field_byline');
$teaser_display->removeComponent('field_photo_credit');
$teaser_display->removeComponent('field_issue');
$teaser_display->removeComponent('field_cancer_types');
$teaser_display->removeComponent('field_content_sections');
$teaser_display->removeComponent('field_related_articles');
$teaser_display->removeComponent('field_homepage_placement');
$teaser_display->removeComponent('field_meta_description');
$teaser_display->removeComponent('field_content_type');

$teaser_display->save();
echo "✅ Article teaser view display configured.\n";

// --- ISSUE: Default (Full) View Display ---
$issue_display = EntityViewDisplay::load('node.issue.default');
if ($issue_display) {
  $issue_display->setComponent('field_banner_image', [
    'type' => 'entity_reference_entity_view',
    'label' => 'hidden',
    'settings' => [
      'view_mode' => 'default',
      'link' => FALSE,
    ],
    'weight' => -10,
    'region' => 'content',
  ]);

  $issue_display->setComponent('field_cover_image', [
    'type' => 'entity_reference_entity_view',
    'label' => 'hidden',
    'settings' => [
      'view_mode' => 'default',
      'link' => FALSE,
    ],
    'weight' => -9,
    'region' => 'content',
  ]);

  $issue_display->setComponent('field_description', [
    'type' => 'text_default',
    'label' => 'hidden',
    'settings' => [],
    'weight' => -5,
    'region' => 'content',
  ]);

  $issue_display->setComponent('field_leadership_message', [
    'type' => 'text_default',
    'label' => 'hidden',
    'settings' => [],
    'weight' => -4,
    'region' => 'content',
  ]);

  $issue_display->setComponent('field_season', [
    'type' => 'list_default',
    'label' => 'hidden',
    'settings' => [],
    'weight' => -8,
    'region' => 'content',
  ]);

  $issue_display->setComponent('field_year', [
    'type' => 'number_integer',
    'label' => 'hidden',
    'settings' => ['thousand_separator' => '', 'prefix_suffix' => true],
    'weight' => -7,
    'region' => 'content',
  ]);

  $issue_display->save();
  echo "✅ Issue default view display configured.\n";
}

// --- ISSUE: Teaser View Display ---
$issue_teaser = EntityViewDisplay::load('node.issue.teaser');
if (!$issue_teaser) {
  $issue_teaser = EntityViewDisplay::create([
    'targetEntityType' => 'node',
    'bundle' => 'issue',
    'mode' => 'teaser',
    'status' => true,
  ]);
}

$issue_teaser->setComponent('field_cover_image', [
  'type' => 'entity_reference_entity_view',
  'label' => 'hidden',
  'settings' => [
    'view_mode' => 'default',
    'link' => FALSE,
  ],
  'weight' => -10,
  'region' => 'content',
]);

$issue_teaser->setComponent('field_season', [
  'type' => 'list_default',
  'label' => 'hidden',
  'settings' => [],
  'weight' => -5,
  'region' => 'content',
]);

$issue_teaser->setComponent('field_year', [
  'type' => 'number_integer',
  'label' => 'hidden',
  'settings' => ['thousand_separator' => '', 'prefix_suffix' => true],
  'weight' => -4,
  'region' => 'content',
]);

$issue_teaser->save();
echo "✅ Issue teaser view display configured.\n";

// --- IN BRIEF: Default View Display ---
$inbrief_display = EntityViewDisplay::load('node.in_brief.default');
if ($inbrief_display) {
  $inbrief_display->setComponent('field_summary', [
    'type' => 'text_default',
    'label' => 'hidden',
    'settings' => [],
    'weight' => 0,
    'region' => 'content',
  ]);

  $inbrief_display->setComponent('field_image', [
    'type' => 'image',
    'label' => 'hidden',
    'settings' => [
      'image_link' => '',
      'image_style' => '',
      'image_loading' => ['attribute' => 'lazy'],
    ],
    'weight' => -5,
    'region' => 'content',
  ]);

  $inbrief_display->setComponent('field_issue', [
    'type' => 'entity_reference_label',
    'label' => 'hidden',
    'settings' => ['link' => true],
    'weight' => 5,
    'region' => 'content',
  ]);

  $inbrief_display->setComponent('field_link', [
    'type' => 'link',
    'label' => 'hidden',
    'settings' => [
      'trim_length' => 80,
      'url_only' => false,
      'url_plain' => false,
      'rel' => '',
      'target' => '',
    ],
    'weight' => 10,
    'region' => 'content',
  ]);

  $inbrief_display->save();
  echo "✅ In Brief default view display configured.\n";
}

// --- PAGE: Default View Display ---
$page_display = EntityViewDisplay::load('node.page.default');
if ($page_display) {
  $field_config = \Drupal\field\Entity\FieldConfig::loadByName('node', 'page', 'field_banner_image');
  if ($field_config) {
    $page_display->setComponent('field_banner_image', [
      'type' => 'entity_reference_entity_view',
      'label' => 'hidden',
      'settings' => [
        'view_mode' => 'default',
        'link' => FALSE,
      ],
      'weight' => -10,
      'region' => 'content',
    ]);
    $page_display->save();
    echo "✅ Page default view display configured.\n";
  }
}

// Enable the teaser view mode for nodes
$view_mode_teaser = \Drupal::entityTypeManager()->getStorage('entity_view_mode')->load('node.teaser');
if ($view_mode_teaser) {
  echo "✅ Teaser view mode already exists.\n";
}

echo "\n🎉 All view displays configured successfully!\n";
