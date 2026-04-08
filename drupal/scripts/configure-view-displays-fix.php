#!/usr/bin/env php
<?php

/**
 * @file
 * Create missing view displays for Issue and In Brief content types.
 */

use Drupal\Core\Entity\Entity\EntityViewDisplay;

// --- ISSUE: Default View Display ---
$issue_display = EntityViewDisplay::load('node.issue.default');
if (!$issue_display) {
  $issue_display = EntityViewDisplay::create([
    'targetEntityType' => 'node',
    'bundle' => 'issue',
    'mode' => 'default',
    'status' => true,
  ]);
}

$issue_display->setComponent('field_banner_image', [
  'type' => 'image',
  'label' => 'hidden',
  'settings' => [
    'image_link' => '',
    'image_style' => '',
    'image_loading' => ['attribute' => 'eager'],
  ],
  'weight' => -10,
  'region' => 'content',
]);

$issue_display->setComponent('field_cover_image', [
  'type' => 'image',
  'label' => 'hidden',
  'settings' => [
    'image_link' => '',
    'image_style' => 'issue_cover',
    'image_loading' => ['attribute' => 'eager'],
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

// --- IN BRIEF: Default View Display ---
$inbrief_display = EntityViewDisplay::load('node.in_brief.default');
if (!$inbrief_display) {
  $inbrief_display = EntityViewDisplay::create([
    'targetEntityType' => 'node',
    'bundle' => 'in_brief',
    'mode' => 'default',
    'status' => true,
  ]);
}

$inbrief_display->setComponent('field_summary', [
  'type' => 'text_default',
  'label' => 'hidden',
  'settings' => [],
  'weight' => 0,
  'region' => 'content',
]);

// Check if field_image exists on in_brief
$field_config = \Drupal\field\Entity\FieldConfig::loadByName('node', 'in_brief', 'field_image');
if ($field_config) {
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
}

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

echo "\n🎉 Done!\n";
