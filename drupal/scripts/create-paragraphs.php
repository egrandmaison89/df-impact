<?php

/**
 * @file
 * Creates Paragraph types and attaches paragraph fields to content types.
 *
 * Run with: ddev drush scr scripts/create-paragraphs.php
 */

use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

/**
 * Helper: Create a field storage if it doesn't exist.
 */
function _p_field_storage($field_name, $entity_type, $type, $settings = []) {
  if (!FieldStorageConfig::loadByName($entity_type, $field_name)) {
    $config = [
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'type' => $type,
      'cardinality' => $settings['cardinality'] ?? 1,
    ];
    if (isset($settings['settings'])) {
      $config['settings'] = $settings['settings'];
    }
    FieldStorageConfig::create($config)->save();
    echo "  Created field storage: $field_name\n";
  }
}

/**
 * Helper: Attach a field to a paragraph bundle.
 */
function _p_field_instance($field_name, $bundle, $label, $settings = []) {
  $entity_type = 'paragraph';
  if (!FieldConfig::loadByName($entity_type, $bundle, $field_name)) {
    $config = [
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'label' => $label,
      'required' => $settings['required'] ?? FALSE,
      'description' => $settings['description'] ?? '',
    ];
    if (isset($settings['settings'])) {
      $config['settings'] = $settings['settings'];
    }
    FieldConfig::create($config)->save();
    echo "  Attached: $field_name -> $bundle\n";
  }
}

// =========================================================================
// Paragraph Type: Pull Quote
// =========================================================================
echo "\n=== Creating Paragraph: Pull Quote ===\n";
if (!ParagraphsType::load('pull_quote')) {
  ParagraphsType::create([
    'id' => 'pull_quote',
    'label' => 'Pull Quote',
    'description' => 'A highlighted quote with optional attribution.',
  ])->save();
  echo "Created paragraph type: Pull Quote\n";
}

_p_field_storage('field_quote_text', 'paragraph', 'text_long');
_p_field_instance('field_quote_text', 'pull_quote', 'Quote Text', [
  'required' => TRUE,
  'description' => 'The quoted text.',
]);

_p_field_storage('field_attribution', 'paragraph', 'string', [
  'settings' => ['max_length' => 255],
]);
_p_field_instance('field_attribution', 'pull_quote', 'Attribution', [
  'required' => FALSE,
  'description' => 'Speaker name and title.',
]);

// =========================================================================
// Paragraph Type: Image with Caption
// =========================================================================
echo "\n=== Creating Paragraph: Image with Caption ===\n";
if (!ParagraphsType::load('image_caption')) {
  ParagraphsType::create([
    'id' => 'image_caption',
    'label' => 'Image with Caption',
    'description' => 'An image with caption and display options.',
  ])->save();
  echo "Created paragraph type: Image with Caption\n";
}

_p_field_storage('field_para_image', 'paragraph', 'entity_reference', [
  'settings' => ['target_type' => 'media'],
]);
_p_field_instance('field_para_image', 'image_caption', 'Image', [
  'required' => TRUE,
  'description' => 'Select or upload an image.',
  'settings' => [
    'handler' => 'default:media',
    'handler_settings' => [
      'target_bundles' => ['image' => 'image'],
    ],
  ],
]);

_p_field_storage('field_caption', 'paragraph', 'string', [
  'settings' => ['max_length' => 500],
]);
_p_field_instance('field_caption', 'image_caption', 'Caption', [
  'required' => FALSE,
  'description' => 'Caption text for the image.',
]);

_p_field_storage('field_display_mode', 'paragraph', 'list_string', [
  'settings' => [
    'allowed_values' => [
      'full_width' => 'Full Width',
      'half_left' => 'Half Width (Left)',
      'half_right' => 'Half Width (Right)',
    ],
  ],
]);
_p_field_instance('field_display_mode', 'image_caption', 'Display Mode', [
  'required' => TRUE,
  'description' => 'How the image should be displayed in the article.',
]);

// =========================================================================
// Paragraph Type: Text Block
// =========================================================================
echo "\n=== Creating Paragraph: Text Block ===\n";
if (!ParagraphsType::load('text_block')) {
  ParagraphsType::create([
    'id' => 'text_block',
    'label' => 'Text Block',
    'description' => 'A rich text content block.',
  ])->save();
  echo "Created paragraph type: Text Block\n";
}

_p_field_storage('field_text_body', 'paragraph', 'text_long');
_p_field_instance('field_text_body', 'text_block', 'Body', [
  'required' => TRUE,
  'description' => 'Rich text content.',
]);

// =========================================================================
// Paragraph Type: Call to Action
// =========================================================================
echo "\n=== Creating Paragraph: Call to Action ===\n";
if (!ParagraphsType::load('cta')) {
  ParagraphsType::create([
    'id' => 'cta',
    'label' => 'Call to Action',
    'description' => 'A call-to-action block with heading, text, and button.',
  ])->save();
  echo "Created paragraph type: Call to Action\n";
}

_p_field_storage('field_cta_heading', 'paragraph', 'string', [
  'settings' => ['max_length' => 255],
]);
_p_field_instance('field_cta_heading', 'cta', 'Heading', [
  'required' => TRUE,
  'description' => 'CTA title.',
]);

_p_field_storage('field_cta_body', 'paragraph', 'text_long');
_p_field_instance('field_cta_body', 'cta', 'Body', [
  'required' => FALSE,
  'description' => 'Supporting text.',
]);

_p_field_storage('field_cta_button_text', 'paragraph', 'string', [
  'settings' => ['max_length' => 100],
]);
_p_field_instance('field_cta_button_text', 'cta', 'Button Text', [
  'required' => TRUE,
  'description' => 'Button label (e.g., "Donate Now").',
]);

_p_field_storage('field_cta_button_url', 'paragraph', 'link');
_p_field_instance('field_cta_button_url', 'cta', 'Button URL', [
  'required' => TRUE,
  'description' => 'Button destination URL.',
]);

// =========================================================================
// Paragraph Type: Stat Highlight
// =========================================================================
echo "\n=== Creating Paragraph: Stat Highlight ===\n";
if (!ParagraphsType::load('stat_highlight')) {
  ParagraphsType::create([
    'id' => 'stat_highlight',
    'label' => 'Stat Highlight',
    'description' => 'A highlighted statistic with label and description.',
  ])->save();
  echo "Created paragraph type: Stat Highlight\n";
}

_p_field_storage('field_stat_number', 'paragraph', 'string', [
  'settings' => ['max_length' => 50],
]);
_p_field_instance('field_stat_number', 'stat_highlight', 'Number', [
  'required' => TRUE,
  'description' => 'The statistic value (e.g., "$72M", "1,000+").',
]);

_p_field_storage('field_stat_label', 'paragraph', 'string', [
  'settings' => ['max_length' => 255],
]);
_p_field_instance('field_stat_label', 'stat_highlight', 'Label', [
  'required' => TRUE,
  'description' => 'What the number represents (e.g., "raised in 2024").',
]);

_p_field_storage('field_stat_description', 'paragraph', 'text_long');
_p_field_instance('field_stat_description', 'stat_highlight', 'Description', [
  'required' => FALSE,
  'description' => 'Optional additional context.',
]);

// =========================================================================
// Paragraph Type: Video Embed
// =========================================================================
echo "\n=== Creating Paragraph: Video Embed ===\n";
if (!ParagraphsType::load('video_embed')) {
  ParagraphsType::create([
    'id' => 'video_embed',
    'label' => 'Video Embed',
    'description' => 'An embedded video from YouTube or Vimeo.',
  ])->save();
  echo "Created paragraph type: Video Embed\n";
}

_p_field_storage('field_video_url', 'paragraph', 'link');
_p_field_instance('field_video_url', 'video_embed', 'Video URL', [
  'required' => TRUE,
  'description' => 'YouTube or Vimeo URL.',
]);

_p_field_storage('field_video_caption', 'paragraph', 'string', [
  'settings' => ['max_length' => 500],
]);
_p_field_instance('field_video_caption', 'video_embed', 'Caption', [
  'required' => FALSE,
  'description' => 'Optional video caption.',
]);

// =========================================================================
// Attach paragraph reference fields to content types
// =========================================================================
echo "\n=== Attaching paragraph fields to content types ===\n";

// field_content_sections on Article (for pull quotes and inline components)
if (!FieldStorageConfig::loadByName('node', 'field_content_sections')) {
  FieldStorageConfig::create([
    'field_name' => 'field_content_sections',
    'entity_type' => 'node',
    'type' => 'entity_reference_revisions',
    'cardinality' => -1,
    'settings' => ['target_type' => 'paragraph'],
  ])->save();
  echo "  Created field storage: field_content_sections\n";
}

// Attach to Article
if (!FieldConfig::loadByName('node', 'article', 'field_content_sections')) {
  FieldConfig::create([
    'field_name' => 'field_content_sections',
    'entity_type' => 'node',
    'bundle' => 'article',
    'label' => 'Content Sections',
    'required' => FALSE,
    'description' => 'Add structured components like pull quotes, images, CTAs, and stats.',
    'settings' => [
      'handler' => 'default:paragraph',
      'handler_settings' => [
        'target_bundles' => [
          'pull_quote' => 'pull_quote',
          'image_caption' => 'image_caption',
          'text_block' => 'text_block',
          'cta' => 'cta',
          'stat_highlight' => 'stat_highlight',
          'video_embed' => 'video_embed',
        ],
        'target_bundles_drag_drop' => [
          'pull_quote' => ['enabled' => TRUE, 'weight' => 0],
          'image_caption' => ['enabled' => TRUE, 'weight' => 1],
          'text_block' => ['enabled' => TRUE, 'weight' => 2],
          'cta' => ['enabled' => TRUE, 'weight' => 3],
          'stat_highlight' => ['enabled' => TRUE, 'weight' => 4],
          'video_embed' => ['enabled' => TRUE, 'weight' => 5],
        ],
      ],
    ],
  ])->save();
  echo "  Attached: field_content_sections -> article\n";
}

// Attach to Page
if (!FieldConfig::loadByName('node', 'page', 'field_content_sections')) {
  FieldConfig::create([
    'field_name' => 'field_content_sections',
    'entity_type' => 'node',
    'bundle' => 'page',
    'label' => 'Content Sections',
    'required' => FALSE,
    'description' => 'Add structured components to this page.',
    'settings' => [
      'handler' => 'default:paragraph',
      'handler_settings' => [
        'target_bundles' => [
          'pull_quote' => 'pull_quote',
          'image_caption' => 'image_caption',
          'text_block' => 'text_block',
          'cta' => 'cta',
          'stat_highlight' => 'stat_highlight',
          'video_embed' => 'video_embed',
        ],
      ],
    ],
  ])->save();
  echo "  Attached: field_content_sections -> page\n";
}

echo "\n=== All paragraph types created and attached successfully ===\n";
