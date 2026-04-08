<?php

/**
 * @file
 * Configures form display for Article content type with field groups.
 *
 * Run with: ddev drush scr scripts/configure-form-display.php
 */

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field_group\Entity\Group;

// =========================================================================
// Article Form Display
// =========================================================================
echo "=== Configuring Article Form Display ===\n";

$form_display = EntityFormDisplay::load('node.article.default');
if (!$form_display) {
  $form_display = EntityFormDisplay::create([
    'targetEntityType' => 'node',
    'bundle' => 'article',
    'mode' => 'default',
    'status' => TRUE,
  ]);
}

// Set field weights and widget types for optimal editorial ordering.
$field_configs = [
  'title' => ['weight' => 0, 'type' => 'string_textfield'],
  'field_subtitle' => ['weight' => 1, 'type' => 'string_textfield'],
  'field_byline' => ['weight' => 2, 'type' => 'string_textfield'],
  'field_photo_credit' => ['weight' => 3, 'type' => 'string_textfield'],
  'body' => ['weight' => 4, 'type' => 'text_textarea_with_summary', 'settings' => ['rows' => 20, 'summary_rows' => 3]],
  'field_content_sections' => ['weight' => 5, 'type' => 'entity_reference_paragraphs', 'settings' => ['title' => 'Component', 'title_plural' => 'Components', 'edit_mode' => 'open', 'add_mode' => 'dropdown']],
  'field_featured_image' => ['weight' => 6, 'type' => 'media_library_widget', 'settings' => ['media_types' => ['image']]],
  'field_issue' => ['weight' => 10, 'type' => 'entity_reference_autocomplete', 'region' => 'sidebar'],
  'field_topics' => ['weight' => 11, 'type' => 'entity_reference_autocomplete_tags', 'region' => 'sidebar'],
  'field_cancer_types' => ['weight' => 12, 'type' => 'entity_reference_autocomplete_tags', 'region' => 'sidebar'],
  'field_content_type' => ['weight' => 13, 'type' => 'options_select', 'region' => 'sidebar'],
  'field_homepage_placement' => ['weight' => 14, 'type' => 'options_select', 'region' => 'sidebar'],
  'field_excerpt' => ['weight' => 15, 'type' => 'text_textarea', 'settings' => ['rows' => 3], 'region' => 'sidebar'],
  'field_meta_description' => ['weight' => 16, 'type' => 'string_textfield', 'region' => 'sidebar'],
  'field_related_articles' => ['weight' => 17, 'type' => 'entity_reference_autocomplete', 'region' => 'sidebar'],
];

foreach ($field_configs as $field_name => $config) {
  $component = [
    'weight' => $config['weight'],
    'type' => $config['type'],
    'settings' => $config['settings'] ?? [],
    'region' => 'content',
  ];
  $form_display->setComponent($field_name, $component);
}

// Hide fields we don't need on the form.
$form_display->removeComponent('field_image');

$form_display->save();
echo "Article form display configured.\n";

// =========================================================================
// Field Groups for Article (using third_party_settings on form display)
// =========================================================================
echo "\n=== Creating Field Groups for Article ===\n";

// Reload form display to add field groups via third_party_settings.
$form_display = EntityFormDisplay::load('node.article.default');

$groups = [
  'group_content' => [
    'label' => 'Content',
    'children' => ['title', 'field_subtitle', 'field_byline', 'field_photo_credit', 'body', 'field_content_sections', 'field_featured_image'],
    'weight' => 0,
    'format_type' => 'tab',
    'format_settings' => [
      'direction' => 'vertical',
      'label' => 'Content',
    ],
  ],
  'group_metadata' => [
    'label' => 'Metadata',
    'children' => ['field_issue', 'field_topics', 'field_cancer_types', 'field_content_type', 'field_homepage_placement'],
    'weight' => 1,
    'format_type' => 'tab',
    'format_settings' => [
      'direction' => 'vertical',
      'label' => 'Metadata',
    ],
  ],
  'group_seo' => [
    'label' => 'SEO & Summary',
    'children' => ['field_excerpt', 'field_meta_description', 'field_related_articles'],
    'weight' => 2,
    'format_type' => 'tab',
    'format_settings' => [
      'direction' => 'vertical',
      'label' => 'SEO & Summary',
    ],
  ],
];

$third_party = [];
foreach ($groups as $group_name => $group_config) {
  $third_party[$group_name] = [
    'children' => $group_config['children'],
    'label' => $group_config['label'],
    'region' => 'content',
    'parent_name' => '',
    'weight' => $group_config['weight'],
    'format_type' => $group_config['format_type'],
    'format_settings' => $group_config['format_settings'],
  ];
  echo "Created field group: {$group_config['label']}\n";
}

$form_display->setThirdPartySetting('field_group', 'group_content', $third_party['group_content']);
$form_display->setThirdPartySetting('field_group', 'group_metadata', $third_party['group_metadata']);
$form_display->setThirdPartySetting('field_group', 'group_seo', $third_party['group_seo']);
$form_display->save();

echo "Field groups saved to Article form display.\n";

// =========================================================================
// Issue Form Display
// =========================================================================
echo "\n=== Configuring Issue Form Display ===\n";

$issue_form = EntityFormDisplay::load('node.issue.default');
if (!$issue_form) {
  $issue_form = EntityFormDisplay::create([
    'targetEntityType' => 'node',
    'bundle' => 'issue',
    'mode' => 'default',
    'status' => TRUE,
  ]);
}

$issue_fields = [
  'title' => ['weight' => 0, 'type' => 'string_textfield'],
  'field_season' => ['weight' => 1, 'type' => 'options_select'],
  'field_year' => ['weight' => 2, 'type' => 'number'],
  'field_cover_image' => ['weight' => 3, 'type' => 'media_library_widget', 'settings' => ['media_types' => ['image']]],
  'field_banner_image' => ['weight' => 4, 'type' => 'media_library_widget', 'settings' => ['media_types' => ['image']]],
  'field_description' => ['weight' => 5, 'type' => 'text_textarea', 'settings' => ['rows' => 5]],
  'field_leadership_message' => ['weight' => 6, 'type' => 'entity_reference_autocomplete'],
];

foreach ($issue_fields as $field_name => $config) {
  $issue_form->setComponent($field_name, [
    'weight' => $config['weight'],
    'type' => $config['type'],
    'settings' => $config['settings'] ?? [],
    'region' => 'content',
  ]);
}

$issue_form->save();
echo "Issue form display configured.\n";

// =========================================================================
// In Brief Form Display
// =========================================================================
echo "\n=== Configuring In Brief Form Display ===\n";

$brief_form = EntityFormDisplay::load('node.in_brief.default');
if (!$brief_form) {
  $brief_form = EntityFormDisplay::create([
    'targetEntityType' => 'node',
    'bundle' => 'in_brief',
    'mode' => 'default',
    'status' => TRUE,
  ]);
}

$brief_fields = [
  'title' => ['weight' => 0, 'type' => 'string_textfield'],
  'field_summary' => ['weight' => 1, 'type' => 'text_textarea', 'settings' => ['rows' => 8]],
  'field_image' => ['weight' => 2, 'type' => 'media_library_widget', 'settings' => ['media_types' => ['image']]],
  'field_issue' => ['weight' => 3, 'type' => 'entity_reference_autocomplete'],
  'field_link' => ['weight' => 4, 'type' => 'link_default'],
];

foreach ($brief_fields as $field_name => $config) {
  $brief_form->setComponent($field_name, [
    'weight' => $config['weight'],
    'type' => $config['type'],
    'settings' => $config['settings'] ?? [],
    'region' => 'content',
  ]);
}

$brief_form->save();
echo "In Brief form display configured.\n";

echo "\n=== All form displays configured successfully ===\n";
