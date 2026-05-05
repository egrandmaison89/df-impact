<?php

/**
 * @file
 * Creates content types and fields for Dana-Farber Impact Magazine.
 *
 * Run with: ddev drush scr scripts/create-content-types.php
 */

use Drupal\node\Entity\NodeType;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

/**
 * Helper: Create a field storage if it doesn't exist.
 */
function _create_field_storage($field_name, $entity_type, $type, $settings = []) {
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
    echo "  Created field storage: $field_name ($type)\n";
  }
  else {
    echo "  Field storage exists: $field_name\n";
  }
}

/**
 * Helper: Attach a field to a bundle.
 */
function _create_field_instance($field_name, $entity_type, $bundle, $label, $settings = []) {
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
    if (isset($settings['default_value'])) {
      $config['default_value'] = $settings['default_value'];
    }
    FieldConfig::create($config)->save();
    echo "  Attached field: $field_name -> $bundle ($label)\n";
  }
  else {
    echo "  Field instance exists: $field_name -> $bundle\n";
  }
}

// =========================================================================
// CONTENT TYPE: Issue
// =========================================================================
echo "\n=== Creating content type: Issue ===\n";
if (!NodeType::load('issue')) {
  NodeType::create([
    'type' => 'issue',
    'name' => 'Issue',
    'description' => 'A quarterly edition of Impact Magazine (e.g., Spring 2026).',
    'display_submitted' => FALSE,
  ])->save();
  echo "Created content type: Issue\n";
}

// field_season
_create_field_storage('field_season', 'node', 'list_string', [
  'settings' => [
    'allowed_values' => [
      'spring' => 'Spring',
      'summer' => 'Summer',
      'fall' => 'Fall',
      'winter' => 'Winter',
    ],
  ],
]);
_create_field_instance('field_season', 'node', 'issue', 'Season', [
  'required' => TRUE,
  'description' => 'The season for this issue.',
]);

// field_year
_create_field_storage('field_year', 'node', 'integer');
_create_field_instance('field_year', 'node', 'issue', 'Year', [
  'required' => TRUE,
  'description' => 'The publication year (e.g., 2026).',
  'settings' => [
    'min' => 2020,
    'max' => 2040,
  ],
]);

// field_cover_image (entity reference to media)
_create_field_storage('field_cover_image', 'node', 'entity_reference', [
  'settings' => ['target_type' => 'media'],
]);
_create_field_instance('field_cover_image', 'node', 'issue', 'Cover Image', [
  'required' => TRUE,
  'description' => 'The magazine cover image (recommended: 1500×1941px).',
  'settings' => [
    'handler' => 'default:media',
    'handler_settings' => [
      'target_bundles' => ['image' => 'image'],
    ],
  ],
]);

// field_banner_image (entity reference to media)
_create_field_storage('field_banner_image', 'node', 'entity_reference', [
  'settings' => ['target_type' => 'media'],
]);
_create_field_instance('field_banner_image', 'node', 'issue', 'Banner Image', [
  'required' => TRUE,
  'description' => 'The issue page hero banner (recommended: 1750×677px).',
  'settings' => [
    'handler' => 'default:media',
    'handler_settings' => [
      'target_bundles' => ['image' => 'image'],
    ],
  ],
]);

// field_description
_create_field_storage('field_description', 'node', 'text_long');
_create_field_instance('field_description', 'node', 'issue', 'Description', [
  'required' => FALSE,
  'description' => 'Optional editorial introduction for this issue.',
]);

// field_leadership_message (entity reference to article node)
_create_field_storage('field_leadership_message', 'node', 'entity_reference', [
  'settings' => ['target_type' => 'node'],
]);
_create_field_instance('field_leadership_message', 'node', 'issue', 'First featured link', [
  'required' => FALSE,
  'description' => 'Large promo below In Brief (e.g. From the Chief Philanthropy Officer). Article or page.',
  'settings' => [
    'handler' => 'default:node',
    'handler_settings' => [
      'target_bundles' => [
        'article' => 'article',
        'page' => 'page',
      ],
    ],
  ],
]);

_create_field_storage('field_issue_promo_2', 'node', 'entity_reference', [
  'settings' => ['target_type' => 'node'],
]);
_create_field_instance('field_issue_promo_2', 'node', 'issue', 'Second featured link', [
  'required' => FALSE,
  'description' => 'Optional second large promo below In Brief (e.g. Future Cancer Hospital page).',
  'settings' => [
    'handler' => 'default:node',
    'handler_settings' => [
      'target_bundles' => [
        'article' => 'article',
        'page' => 'page',
      ],
    ],
  ],
]);

// =========================================================================
// CONTENT TYPE: Article
// =========================================================================
echo "\n=== Creating content type: Article ===\n";
// Delete the default 'article' content type from standard profile and recreate
$existing = NodeType::load('article');
if ($existing) {
  // The standard profile creates an 'article' type. We need to customize it.
  $existing->set('description', 'A magazine article or story for Impact Magazine.');
  $existing->set('display_submitted', TRUE);
  $existing->save();
  echo "Updated existing content type: Article\n";
}

// field_subtitle
_create_field_storage('field_subtitle', 'node', 'string', [
  'settings' => ['max_length' => 500],
]);
_create_field_instance('field_subtitle', 'node', 'article', 'Subtitle', [
  'required' => FALSE,
  'description' => 'A secondary headline or deck (optional).',
]);

// field_byline
_create_field_storage('field_byline', 'node', 'string', [
  'settings' => ['max_length' => 255],
]);
_create_field_instance('field_byline', 'node', 'article', 'Byline', [
  'required' => TRUE,
  'description' => 'The author name, e.g., "By Amber Sinicrope".',
]);

// field_photo_credit
_create_field_storage('field_photo_credit', 'node', 'string', [
  'settings' => ['max_length' => 255],
]);
_create_field_instance('field_photo_credit', 'node', 'article', 'Photo Credit', [
  'required' => FALSE,
  'description' => 'Photographer credit, e.g., "Photography by Bryce Vickmark".',
]);

// field_featured_image (entity reference to media)
_create_field_storage('field_featured_image', 'node', 'entity_reference', [
  'settings' => ['target_type' => 'media'],
]);
_create_field_instance('field_featured_image', 'node', 'article', 'Featured Image', [
  'required' => TRUE,
  'description' => 'The main article image (recommended: 1450×1040px minimum).',
  'settings' => [
    'handler' => 'default:media',
    'handler_settings' => [
      'target_bundles' => ['image' => 'image'],
    ],
  ],
]);

// field_excerpt
_create_field_storage('field_excerpt', 'node', 'text_long');
_create_field_instance('field_excerpt', 'node', 'article', 'Excerpt', [
  'required' => FALSE,
  'description' => 'A 1-2 sentence summary. Leave blank to auto-generate from body.',
]);

// field_issue (entity reference to Issue node)
_create_field_storage('field_issue', 'node', 'entity_reference', [
  'settings' => ['target_type' => 'node'],
]);
_create_field_instance('field_issue', 'node', 'article', 'Issue', [
  'required' => FALSE,
  'description' => 'The quarterly magazine issue when this story ran in print. Omit for web-only exclusives if there is no print issue.',
  'settings' => [
    'handler' => 'default:node',
    'handler_settings' => [
      'target_bundles' => ['issue' => 'issue'],
    ],
  ],
]);

// field_topics (entity reference to Topics taxonomy, multi-value)
_create_field_storage('field_topics', 'node', 'entity_reference', [
  'cardinality' => -1,
  'settings' => ['target_type' => 'taxonomy_term'],
]);
_create_field_instance('field_topics', 'node', 'article', 'Topics', [
  'required' => TRUE,
  'description' => 'Select one or more editorial topics.',
  'settings' => [
    'handler' => 'default:taxonomy_term',
    'handler_settings' => [
      'target_bundles' => ['topics' => 'topics'],
    ],
  ],
]);

// field_channels (taxonomy: site sections / Digital Exclusives)
_create_field_storage('field_channels', 'node', 'entity_reference', [
  'cardinality' => -1,
  'settings' => ['target_type' => 'taxonomy_term'],
]);
_create_field_instance('field_channels', 'node', 'article', 'Channels', [
  'required' => FALSE,
  'description' => 'Assign Digital Exclusives to list this story in the homepage Digital Exclusives row and on /digital-exclusives.',
  'settings' => [
    'handler' => 'default:taxonomy_term',
    'handler_settings' => [
      'target_bundles' => ['channels' => 'channels'],
    ],
  ],
]);

// field_cancer_types (entity reference to Cancer Types taxonomy, multi-value)
_create_field_storage('field_cancer_types', 'node', 'entity_reference', [
  'cardinality' => -1,
  'settings' => ['target_type' => 'taxonomy_term'],
]);
_create_field_instance('field_cancer_types', 'node', 'article', 'Cancer Types', [
  'required' => FALSE,
  'description' => 'Select applicable cancer types (optional).',
  'settings' => [
    'handler' => 'default:taxonomy_term',
    'handler_settings' => [
      'target_bundles' => ['cancer_types' => 'cancer_types'],
    ],
  ],
]);

// field_content_type (entity reference to Content Type taxonomy)
_create_field_storage('field_content_type', 'node', 'entity_reference', [
  'settings' => ['target_type' => 'taxonomy_term'],
]);
_create_field_instance('field_content_type', 'node', 'article', 'Content Type', [
  'required' => FALSE,
  'description' => 'The format of this article (Feature Story, Donor Profile, etc.).',
  'settings' => [
    'handler' => 'default:taxonomy_term',
    'handler_settings' => [
      'target_bundles' => ['content_type' => 'content_type'],
    ],
  ],
]);

// field_homepage_placement
_create_field_storage('field_homepage_placement', 'node', 'list_string', [
  'settings' => [
    'allowed_values' => [
      'none' => 'None',
      'featured' => 'Featured (Hero Section)',
      'recent_highlights' => 'Recent Highlights',
    ],
  ],
]);
_create_field_instance('field_homepage_placement', 'node', 'article', 'Homepage Placement', [
  'required' => FALSE,
  'description' => 'Where this article should appear on the homepage.',
  'default_value' => [['value' => 'none']],
]);

// field_related_articles (entity reference to article nodes, multi-value)
_create_field_storage('field_related_articles', 'node', 'entity_reference', [
  'cardinality' => 3,
  'settings' => ['target_type' => 'node'],
]);
_create_field_instance('field_related_articles', 'node', 'article', 'Related Articles', [
  'required' => FALSE,
  'description' => 'Manually select up to 3 related articles. Leave empty for auto-selection.',
  'settings' => [
    'handler' => 'default:node',
    'handler_settings' => [
      'target_bundles' => ['article' => 'article'],
    ],
  ],
]);

// field_meta_description
_create_field_storage('field_meta_description', 'node', 'string', [
  'settings' => ['max_length' => 320],
]);
_create_field_instance('field_meta_description', 'node', 'article', 'Meta Description', [
  'required' => FALSE,
  'description' => 'SEO meta description (max 320 characters). Falls back to excerpt if empty.',
]);

// field_pull_quotes (paragraphs - will attach after paragraph types are created)
// Skipped here — will be configured separately.

// =========================================================================
// CONTENT TYPE: In Brief
// =========================================================================
echo "\n=== Creating content type: In Brief ===\n";
if (!NodeType::load('in_brief')) {
  NodeType::create([
    'type' => 'in_brief',
    'name' => 'In Brief',
    'description' => 'Short-form content items for the "In Brief" section.',
    'display_submitted' => FALSE,
  ])->save();
  echo "Created content type: In Brief\n";
}

// field_summary
_create_field_storage('field_summary', 'node', 'text_long');
_create_field_instance('field_summary', 'node', 'in_brief', 'Summary', [
  'required' => TRUE,
  'description' => 'The brief content summary.',
]);

// field_image (entity reference to media) — reuse storage if possible
_create_field_storage('field_image', 'node', 'entity_reference', [
  'settings' => ['target_type' => 'media'],
]);
_create_field_instance('field_image', 'node', 'in_brief', 'Image', [
  'required' => FALSE,
  'description' => 'Supporting image for this brief.',
  'settings' => [
    'handler' => 'default:media',
    'handler_settings' => [
      'target_bundles' => ['image' => 'image'],
    ],
  ],
]);

// Attach field_issue to in_brief
_create_field_instance('field_issue', 'node', 'in_brief', 'Issue', [
  'required' => TRUE,
  'description' => 'The quarterly issue this item belongs to.',
  'settings' => [
    'handler' => 'default:node',
    'handler_settings' => [
      'target_bundles' => ['issue' => 'issue'],
    ],
  ],
]);

// field_link
_create_field_storage('field_link', 'node', 'link');
_create_field_instance('field_link', 'node', 'in_brief', 'Link', [
  'required' => FALSE,
  'description' => 'Optional link to a full article or external resource.',
]);

// =========================================================================
// Update default Page content type
// =========================================================================
echo "\n=== Updating content type: Page ===\n";

// Attach field_banner_image to page
_create_field_instance('field_banner_image', 'node', 'page', 'Banner Image', [
  'required' => FALSE,
  'description' => 'Optional hero banner image for this page.',
  'settings' => [
    'handler' => 'default:media',
    'handler_settings' => [
      'target_bundles' => ['image' => 'image'],
    ],
  ],
]);

// field_content_sections (paragraphs) - will be configured after paragraph types

echo "\n=== All content types and fields created successfully ===\n";
