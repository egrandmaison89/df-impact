<?php

/**
 * @file
 * Configures CKEditor 5 text formats for editorial use.
 *
 * Run with: ddev drush scr scripts/configure-ckeditor.php
 */

$config_factory = \Drupal::configFactory();

// =========================================================================
// Configure the "Full HTML" text format for article body
// =========================================================================
echo "=== Configuring Full HTML text format ===\n";

$full_html = $config_factory->getEditable('filter.format.full_html');
if (!$full_html->isNew()) {
  // Update existing full_html format.
  $full_html->set('name', 'Full HTML');
  $full_html->set('weight', -10);
  $full_html->set('roles', ['administrator', 'editor', 'issue_manager', 'content_editor']);
  $full_html->save();
  echo "Updated Full HTML format roles.\n";
}

// Configure CKEditor 5 for full_html.
$editor_config = $config_factory->getEditable('editor.editor.full_html');
if (!$editor_config->isNew()) {
  $settings = $editor_config->get('settings');

  // Set the toolbar items.
  $settings['toolbar']['items'] = [
    'heading',
    '|',
    'bold',
    'italic',
    '|',
    'link',
    '|',
    'bulletedList',
    'numberedList',
    '|',
    'blockQuote',
    '|',
    'drupalInsertImage',
    'drupalMedia',
    '|',
    'insertTable',
    '|',
    'sourceEditing',
    '|',
    'undo',
    'redo',
  ];

  $editor_config->set('settings', $settings);
  $editor_config->save();
  echo "Configured CKEditor 5 toolbar for Full HTML.\n";
}

// =========================================================================
// Configure the "Basic HTML" format for simpler fields
// =========================================================================
echo "\n=== Configuring Basic HTML text format ===\n";

$basic_html = $config_factory->getEditable('filter.format.basic_html');
if (!$basic_html->isNew()) {
  $basic_html->set('roles', ['authenticated', 'editor', 'issue_manager', 'content_editor']);
  $basic_html->save();
  echo "Updated Basic HTML format roles.\n";
}

$basic_editor = $config_factory->getEditable('editor.editor.basic_html');
if (!$basic_editor->isNew()) {
  $settings = $basic_editor->get('settings');

  $settings['toolbar']['items'] = [
    'bold',
    'italic',
    '|',
    'link',
    '|',
    'bulletedList',
    'numberedList',
    '|',
    'blockQuote',
  ];

  $basic_editor->set('settings', $settings);
  $basic_editor->save();
  echo "Configured CKEditor 5 toolbar for Basic HTML.\n";
}

echo "\n=== CKEditor configuration complete ===\n";
echo "\nNote: Linkit integration for CKEditor 5 should be enabled via\n";
echo "/admin/config/content/linkit to create a profile, then\n";
echo "/admin/config/content/formats/manage/full_html to attach it.\n";
