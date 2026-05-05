<?php

/**
 * @file
 * Creates all Views for Dana-Farber Impact Magazine.
 *
 * Run with: ddev drush scr scripts/create-views.php
 */

use Drupal\views\Entity\View;

// =========================================================================
// Helper to save a view from config array
// =========================================================================
function _save_view($id, $config) {
  $existing = View::load($id);
  if ($existing) {
    echo "View already exists: $id — skipping\n";
    return;
  }
  $view = View::create($config);
  $view->save();
  echo "Created view: $id\n";
}

// =========================================================================
// 1. Homepage Featured Articles
// =========================================================================
_save_view('homepage_featured', [
  'id' => 'homepage_featured',
  'label' => 'Homepage: Featured Articles',
  'description' => 'Featured articles for the homepage hero section.',
  'base_table' => 'node_field_data',
  'core' => '10.x',
  'display' => [
    'default' => [
      'id' => 'default',
      'display_title' => 'Default',
      'display_plugin' => 'default',
      'position' => 0,
      'display_options' => [
        'title' => 'Featured Articles',
        'fields' => [
          'title' => [
            'id' => 'title',
            'table' => 'node_field_data',
            'field' => 'title',
            'label' => '',
            'plugin_id' => 'field',
            'type' => 'string',
            'settings' => ['link_to_entity' => TRUE],
          ],
          'field_featured_image' => [
            'id' => 'field_featured_image',
            'table' => 'node__field_featured_image',
            'field' => 'field_featured_image',
            'label' => '',
            'plugin_id' => 'field',
            'type' => 'entity_reference_entity_view',
          ],
          'field_excerpt' => [
            'id' => 'field_excerpt',
            'table' => 'node__field_excerpt',
            'field' => 'field_excerpt',
            'label' => '',
            'plugin_id' => 'field',
          ],
          'field_byline' => [
            'id' => 'field_byline',
            'table' => 'node__field_byline',
            'field' => 'field_byline',
            'label' => '',
            'plugin_id' => 'field',
          ],
          'field_topics' => [
            'id' => 'field_topics',
            'table' => 'node__field_topics',
            'field' => 'field_topics',
            'label' => '',
            'plugin_id' => 'field',
            'type' => 'entity_reference_label',
          ],
        ],
        'filters' => [
          'status' => [
            'id' => 'status',
            'table' => 'node_field_data',
            'field' => 'status',
            'value' => '1',
            'plugin_id' => 'boolean',
            'group' => 1,
          ],
          'type' => [
            'id' => 'type',
            'table' => 'node_field_data',
            'field' => 'type',
            'value' => ['article' => 'article'],
            'plugin_id' => 'bundle',
            'group' => 1,
          ],
          'field_homepage_placement_value' => [
            'id' => 'field_homepage_placement_value',
            'table' => 'node__field_homepage_placement',
            'field' => 'field_homepage_placement_value',
            'value' => ['featured' => 'featured'],
            'plugin_id' => 'list_field',
            'group' => 1,
          ],
        ],
        'sorts' => [
          'sticky' => [
            'id' => 'sticky',
            'table' => 'node_field_data',
            'field' => 'sticky',
            'order' => 'DESC',
            'plugin_id' => 'boolean',
          ],
          'created' => [
            'id' => 'created',
            'table' => 'node_field_data',
            'field' => 'created',
            'order' => 'DESC',
            'plugin_id' => 'date',
          ],
        ],
        'pager' => [
          'type' => 'some',
          'options' => ['items_per_page' => 3],
        ],
        'style' => [
          'type' => 'default',
        ],
        'row' => [
          'type' => 'fields',
        ],
      ],
    ],
    'block_1' => [
      'id' => 'block_1',
      'display_title' => 'Block',
      'display_plugin' => 'block',
      'position' => 1,
      'display_options' => [
        'block_description' => 'Homepage: Featured Articles',
      ],
    ],
  ],
]);

// =========================================================================
// 2. Homepage Recent Highlights
// =========================================================================
_save_view('homepage_highlights', [
  'id' => 'homepage_highlights',
  'label' => 'Homepage: Recent Highlights',
  'description' => 'Recent highlights grid for the homepage.',
  'base_table' => 'node_field_data',
  'core' => '10.x',
  'display' => [
    'default' => [
      'id' => 'default',
      'display_title' => 'Default',
      'display_plugin' => 'default',
      'position' => 0,
      'display_options' => [
        'title' => 'Recent Highlights',
        'fields' => [
          'title' => [
            'id' => 'title',
            'table' => 'node_field_data',
            'field' => 'title',
            'label' => '',
            'plugin_id' => 'field',
            'type' => 'string',
            'settings' => ['link_to_entity' => TRUE],
          ],
          'field_featured_image' => [
            'id' => 'field_featured_image',
            'table' => 'node__field_featured_image',
            'field' => 'field_featured_image',
            'label' => '',
            'plugin_id' => 'field',
          ],
          'field_topics' => [
            'id' => 'field_topics',
            'table' => 'node__field_topics',
            'field' => 'field_topics',
            'label' => '',
            'plugin_id' => 'field',
            'type' => 'entity_reference_label',
          ],
        ],
        'filters' => [
          'status' => [
            'id' => 'status',
            'table' => 'node_field_data',
            'field' => 'status',
            'value' => '1',
            'plugin_id' => 'boolean',
            'group' => 1,
          ],
          'type' => [
            'id' => 'type',
            'table' => 'node_field_data',
            'field' => 'type',
            'value' => ['article' => 'article'],
            'plugin_id' => 'bundle',
            'group' => 1,
          ],
          'field_homepage_placement_value' => [
            'id' => 'field_homepage_placement_value',
            'table' => 'node__field_homepage_placement',
            'field' => 'field_homepage_placement_value',
            'value' => ['recent_highlights' => 'recent_highlights'],
            'plugin_id' => 'list_field',
            'group' => 1,
          ],
        ],
        'sorts' => [
          'created' => [
            'id' => 'created',
            'table' => 'node_field_data',
            'field' => 'created',
            'order' => 'DESC',
            'plugin_id' => 'date',
          ],
        ],
        'pager' => [
          'type' => 'some',
          'options' => ['items_per_page' => 6],
        ],
        'style' => [
          'type' => 'default',
        ],
        'row' => [
          'type' => 'fields',
        ],
      ],
    ],
    'block_1' => [
      'id' => 'block_1',
      'display_title' => 'Block',
      'display_plugin' => 'block',
      'position' => 1,
      'display_options' => [
        'block_description' => 'Homepage: Recent Highlights',
      ],
    ],
  ],
]);

// =========================================================================
// 3. Homepage Digital Exclusives
// =========================================================================
_save_view('homepage_digital_exclusives', [
  'id' => 'homepage_digital_exclusives',
  'label' => 'Homepage: Digital Exclusives',
  'description' => 'Latest Digital Exclusives channel articles for the homepage; channel filter applied in df_setup.module.',
  'base_table' => 'node_field_data',
  'core' => '10.x',
  'display' => [
    'default' => [
      'id' => 'default',
      'display_title' => 'Default',
      'display_plugin' => 'default',
      'position' => 0,
      'display_options' => [
        'title' => 'Digital Exclusives',
        'fields' => [
          'title' => [
            'id' => 'title',
            'table' => 'node_field_data',
            'field' => 'title',
            'label' => '',
            'plugin_id' => 'field',
            'type' => 'string',
            'settings' => ['link_to_entity' => TRUE],
          ],
          'field_featured_image' => [
            'id' => 'field_featured_image',
            'table' => 'node__field_featured_image',
            'field' => 'field_featured_image',
            'label' => '',
            'plugin_id' => 'field',
          ],
          'field_topics' => [
            'id' => 'field_topics',
            'table' => 'node__field_topics',
            'field' => 'field_topics',
            'label' => '',
            'plugin_id' => 'field',
            'type' => 'entity_reference_label',
          ],
        ],
        'filters' => [
          'status' => [
            'id' => 'status',
            'table' => 'node_field_data',
            'field' => 'status',
            'value' => '1',
            'plugin_id' => 'boolean',
            'group' => 1,
          ],
          'type' => [
            'id' => 'type',
            'table' => 'node_field_data',
            'field' => 'type',
            'value' => ['article' => 'article'],
            'plugin_id' => 'bundle',
            'group' => 1,
          ],
        ],
        'sorts' => [
          'sticky' => [
            'id' => 'sticky',
            'table' => 'node_field_data',
            'field' => 'sticky',
            'plugin_id' => 'boolean',
            'order' => 'DESC',
          ],
          'created' => [
            'id' => 'created',
            'table' => 'node_field_data',
            'field' => 'created',
            'order' => 'DESC',
            'plugin_id' => 'date',
          ],
        ],
        'pager' => [
          'type' => 'some',
          'options' => ['items_per_page' => 3],
        ],
        'style' => [
          'type' => 'default',
        ],
        'row' => [
          'type' => 'fields',
        ],
      ],
    ],
    'block_1' => [
      'id' => 'block_1',
      'display_title' => 'Block',
      'display_plugin' => 'block',
      'position' => 1,
      'display_options' => [
        'block_description' => 'Homepage: Digital Exclusives',
      ],
    ],
  ],
]);

// =========================================================================
// 4. Issue Articles
// =========================================================================
_save_view('issue_articles', [
  'id' => 'issue_articles',
  'label' => 'Issue: Articles',
  'description' => 'Lists all articles belonging to a specific issue.',
  'base_table' => 'node_field_data',
  'core' => '10.x',
  'display' => [
    'default' => [
      'id' => 'default',
      'display_title' => 'Default',
      'display_plugin' => 'default',
      'position' => 0,
      'display_options' => [
        'title' => 'Articles in this Issue',
        'fields' => [
          'title' => [
            'id' => 'title',
            'table' => 'node_field_data',
            'field' => 'title',
            'label' => '',
            'plugin_id' => 'field',
            'type' => 'string',
            'settings' => ['link_to_entity' => TRUE],
          ],
          'field_featured_image' => [
            'id' => 'field_featured_image',
            'table' => 'node__field_featured_image',
            'field' => 'field_featured_image',
            'label' => '',
            'plugin_id' => 'field',
          ],
          'field_excerpt' => [
            'id' => 'field_excerpt',
            'table' => 'node__field_excerpt',
            'field' => 'field_excerpt',
            'label' => '',
            'plugin_id' => 'field',
          ],
          'field_topics' => [
            'id' => 'field_topics',
            'table' => 'node__field_topics',
            'field' => 'field_topics',
            'label' => '',
            'plugin_id' => 'field',
            'type' => 'entity_reference_label',
          ],
        ],
        'arguments' => [
          'field_issue_target_id' => [
            'id' => 'field_issue_target_id',
            'table' => 'node__field_issue',
            'field' => 'field_issue_target_id',
            'default_action' => 'empty',
            'title_enable' => TRUE,
            'title' => 'Articles in %1',
            'plugin_id' => 'numeric',
          ],
        ],
        'filters' => [
          'status' => [
            'id' => 'status',
            'table' => 'node_field_data',
            'field' => 'status',
            'value' => '1',
            'plugin_id' => 'boolean',
            'group' => 1,
          ],
          'type' => [
            'id' => 'type',
            'table' => 'node_field_data',
            'field' => 'type',
            'value' => ['article' => 'article'],
            'plugin_id' => 'bundle',
            'group' => 1,
          ],
        ],
        'sorts' => [
          'created' => [
            'id' => 'created',
            'table' => 'node_field_data',
            'field' => 'created',
            'order' => 'DESC',
            'plugin_id' => 'date',
          ],
        ],
        'pager' => [
          'type' => 'full',
          'options' => ['items_per_page' => 10],
        ],
        'style' => [
          'type' => 'default',
        ],
        'row' => [
          'type' => 'fields',
        ],
      ],
    ],
    'block_1' => [
      'id' => 'block_1',
      'display_title' => 'Block',
      'display_plugin' => 'block',
      'position' => 1,
      'display_options' => [
        'block_description' => 'Issue: Articles',
      ],
    ],
  ],
]);

// =========================================================================
// 5. Browse Issues
// =========================================================================
_save_view('browse_issues', [
  'id' => 'browse_issues',
  'label' => 'Browse Issues',
  'description' => 'Grid of all quarterly issues with cover images.',
  'base_table' => 'node_field_data',
  'core' => '10.x',
  'display' => [
    'default' => [
      'id' => 'default',
      'display_title' => 'Default',
      'display_plugin' => 'default',
      'position' => 0,
      'display_options' => [
        'title' => 'Browse Issues',
        'fields' => [
          'title' => [
            'id' => 'title',
            'table' => 'node_field_data',
            'field' => 'title',
            'label' => '',
            'plugin_id' => 'field',
            'type' => 'string',
            'settings' => ['link_to_entity' => TRUE],
          ],
          'field_cover_image' => [
            'id' => 'field_cover_image',
            'table' => 'node__field_cover_image',
            'field' => 'field_cover_image',
            'label' => '',
            'plugin_id' => 'field',
            'type' => 'entity_reference_entity_view',
          ],
          'field_season' => [
            'id' => 'field_season',
            'table' => 'node__field_season',
            'field' => 'field_season',
            'label' => '',
            'plugin_id' => 'field',
            'type' => 'list_default',
          ],
          'field_year' => [
            'id' => 'field_year',
            'table' => 'node__field_year',
            'field' => 'field_year',
            'label' => '',
            'plugin_id' => 'field',
            'type' => 'number_integer',
            'settings' => [
              'thousand_separator' => '',
              'prefix_suffix' => FALSE,
            ],
          ],
        ],
        'filters' => [
          'status' => [
            'id' => 'status',
            'table' => 'node_field_data',
            'field' => 'status',
            'value' => '1',
            'plugin_id' => 'boolean',
            'group' => 1,
          ],
          'type' => [
            'id' => 'type',
            'table' => 'node_field_data',
            'field' => 'type',
            'value' => ['issue' => 'issue'],
            'plugin_id' => 'bundle',
            'group' => 1,
          ],
        ],
        'sorts' => [
          'field_year_value' => [
            'id' => 'field_year_value',
            'table' => 'node__field_year',
            'field' => 'field_year_value',
            'order' => 'DESC',
            'plugin_id' => 'standard',
          ],
          'field_season_value' => [
            'id' => 'field_season_value',
            'table' => 'node__field_season',
            'field' => 'field_season_value',
            'order' => 'DESC',
            'plugin_id' => 'standard',
          ],
        ],
        'pager' => [
          'type' => 'none',
        ],
        'style' => [
          'type' => 'default',
        ],
        'row' => [
          'type' => 'fields',
        ],
      ],
    ],
    'page_1' => [
      'id' => 'page_1',
      'display_title' => 'Page',
      'display_plugin' => 'page',
      'position' => 1,
      'display_options' => [
        'path' => 'issues',
      ],
    ],
  ],
]);

// =========================================================================
// 6. In Brief Listing
// =========================================================================
_save_view('in_brief_listing', [
  'id' => 'in_brief_listing',
  'label' => 'In Brief',
  'description' => 'Lists all In Brief items with pagination.',
  'base_table' => 'node_field_data',
  'core' => '10.x',
  'display' => [
    'default' => [
      'id' => 'default',
      'display_title' => 'Default',
      'display_plugin' => 'default',
      'position' => 0,
      'display_options' => [
        'title' => 'In Brief',
        'fields' => [
          'title' => [
            'id' => 'title',
            'table' => 'node_field_data',
            'field' => 'title',
            'label' => '',
            'plugin_id' => 'field',
            'type' => 'string',
            'settings' => ['link_to_entity' => TRUE],
          ],
          'field_summary' => [
            'id' => 'field_summary',
            'table' => 'node__field_summary',
            'field' => 'field_summary',
            'label' => '',
            'plugin_id' => 'field',
          ],
          'field_image' => [
            'id' => 'field_image',
            'table' => 'node__field_image',
            'field' => 'field_image',
            'label' => '',
            'plugin_id' => 'field',
          ],
          'field_issue' => [
            'id' => 'field_issue',
            'table' => 'node__field_issue',
            'field' => 'field_issue',
            'label' => '',
            'plugin_id' => 'field',
            'type' => 'entity_reference_label',
          ],
        ],
        'filters' => [
          'status' => [
            'id' => 'status',
            'table' => 'node_field_data',
            'field' => 'status',
            'value' => '1',
            'plugin_id' => 'boolean',
            'group' => 1,
          ],
          'type' => [
            'id' => 'type',
            'table' => 'node_field_data',
            'field' => 'type',
            'value' => ['in_brief' => 'in_brief'],
            'plugin_id' => 'bundle',
            'group' => 1,
          ],
        ],
        'sorts' => [
          'created' => [
            'id' => 'created',
            'table' => 'node_field_data',
            'field' => 'created',
            'order' => 'DESC',
            'plugin_id' => 'date',
          ],
        ],
        'pager' => [
          'type' => 'full',
          'options' => ['items_per_page' => 10],
        ],
        'style' => [
          'type' => 'default',
        ],
        'row' => [
          'type' => 'fields',
        ],
      ],
    ],
    'page_1' => [
      'id' => 'page_1',
      'display_title' => 'Page',
      'display_plugin' => 'page',
      'position' => 1,
      'display_options' => [
        'path' => 'in-brief',
      ],
    ],
  ],
]);

// =========================================================================
// 7. Topic Archive
// =========================================================================
_save_view('topic_archive', [
  'id' => 'topic_archive',
  'label' => 'Topic Archive',
  'description' => 'Articles filtered by topic taxonomy term.',
  'base_table' => 'node_field_data',
  'core' => '10.x',
  'display' => [
    'default' => [
      'id' => 'default',
      'display_title' => 'Default',
      'display_plugin' => 'default',
      'position' => 0,
      'display_options' => [
        'title' => 'Articles on %1',
        'fields' => [
          'title' => [
            'id' => 'title',
            'table' => 'node_field_data',
            'field' => 'title',
            'label' => '',
            'plugin_id' => 'field',
            'type' => 'string',
            'settings' => ['link_to_entity' => TRUE],
          ],
          'field_featured_image' => [
            'id' => 'field_featured_image',
            'table' => 'node__field_featured_image',
            'field' => 'field_featured_image',
            'label' => '',
            'plugin_id' => 'field',
          ],
          'field_excerpt' => [
            'id' => 'field_excerpt',
            'table' => 'node__field_excerpt',
            'field' => 'field_excerpt',
            'label' => '',
            'plugin_id' => 'field',
          ],
          'field_topics' => [
            'id' => 'field_topics',
            'table' => 'node__field_topics',
            'field' => 'field_topics',
            'label' => '',
            'plugin_id' => 'field',
            'type' => 'entity_reference_label',
          ],
        ],
        'arguments' => [
          'field_topics_target_id' => [
            'id' => 'field_topics_target_id',
            'table' => 'node__field_topics',
            'field' => 'field_topics_target_id',
            'default_action' => 'not found',
            'title_enable' => TRUE,
            'title' => '%1',
            'plugin_id' => 'numeric',
          ],
        ],
        'filters' => [
          'status' => [
            'id' => 'status',
            'table' => 'node_field_data',
            'field' => 'status',
            'value' => '1',
            'plugin_id' => 'boolean',
            'group' => 1,
          ],
          'type' => [
            'id' => 'type',
            'table' => 'node_field_data',
            'field' => 'type',
            'value' => ['article' => 'article'],
            'plugin_id' => 'bundle',
            'group' => 1,
          ],
        ],
        'sorts' => [
          'created' => [
            'id' => 'created',
            'table' => 'node_field_data',
            'field' => 'created',
            'order' => 'DESC',
            'plugin_id' => 'date',
          ],
        ],
        'pager' => [
          'type' => 'full',
          'options' => ['items_per_page' => 12],
        ],
        'style' => [
          'type' => 'default',
        ],
        'row' => [
          'type' => 'fields',
        ],
      ],
    ],
    'page_1' => [
      'id' => 'page_1',
      'display_title' => 'Page',
      'display_plugin' => 'page',
      'position' => 1,
      'display_options' => [
        'path' => 'topics/%',
      ],
    ],
  ],
]);

// =========================================================================
// 8. Related Articles
// =========================================================================
_save_view('related_articles', [
  'id' => 'related_articles',
  'label' => 'Related Articles',
  'description' => 'Shows related articles based on shared topics.',
  'base_table' => 'node_field_data',
  'core' => '10.x',
  'display' => [
    'default' => [
      'id' => 'default',
      'display_title' => 'Default',
      'display_plugin' => 'default',
      'position' => 0,
      'display_options' => [
        'title' => 'More Stories',
        'fields' => [
          'title' => [
            'id' => 'title',
            'table' => 'node_field_data',
            'field' => 'title',
            'label' => '',
            'plugin_id' => 'field',
            'type' => 'string',
            'settings' => ['link_to_entity' => TRUE],
          ],
          'field_featured_image' => [
            'id' => 'field_featured_image',
            'table' => 'node__field_featured_image',
            'field' => 'field_featured_image',
            'label' => '',
            'plugin_id' => 'field',
          ],
          'field_topics' => [
            'id' => 'field_topics',
            'table' => 'node__field_topics',
            'field' => 'field_topics',
            'label' => '',
            'plugin_id' => 'field',
            'type' => 'entity_reference_label',
          ],
        ],
        'arguments' => [
          'field_topics_target_id' => [
            'id' => 'field_topics_target_id',
            'table' => 'node__field_topics',
            'field' => 'field_topics_target_id',
            'default_action' => 'empty',
            'plugin_id' => 'numeric',
            'break_phrase' => TRUE,
          ],
          'nid' => [
            'id' => 'nid',
            'table' => 'node_field_data',
            'field' => 'nid',
            'default_action' => 'empty',
            'not' => TRUE,
            'plugin_id' => 'node_nid',
          ],
        ],
        'filters' => [
          'status' => [
            'id' => 'status',
            'table' => 'node_field_data',
            'field' => 'status',
            'value' => '1',
            'plugin_id' => 'boolean',
            'group' => 1,
          ],
          'type' => [
            'id' => 'type',
            'table' => 'node_field_data',
            'field' => 'type',
            'value' => ['article' => 'article'],
            'plugin_id' => 'bundle',
            'group' => 1,
          ],
        ],
        'sorts' => [
          'created' => [
            'id' => 'created',
            'table' => 'node_field_data',
            'field' => 'created',
            'order' => 'DESC',
            'plugin_id' => 'date',
          ],
        ],
        'pager' => [
          'type' => 'some',
          'options' => ['items_per_page' => 3],
        ],
        'style' => [
          'type' => 'default',
        ],
        'row' => [
          'type' => 'fields',
        ],
      ],
    ],
    'block_1' => [
      'id' => 'block_1',
      'display_title' => 'Block',
      'display_plugin' => 'block',
      'position' => 1,
      'display_options' => [
        'block_description' => 'Related Articles',
      ],
    ],
  ],
]);

// =========================================================================
// 9. Editorial Dashboard (Admin)
// =========================================================================
_save_view('editorial_dashboard', [
  'id' => 'editorial_dashboard',
  'label' => 'Editorial Dashboard',
  'description' => 'Admin view for managing editorial content.',
  'base_table' => 'node_field_data',
  'core' => '10.x',
  'display' => [
    'default' => [
      'id' => 'default',
      'display_title' => 'Default',
      'display_plugin' => 'default',
      'position' => 0,
      'display_options' => [
        'title' => 'Editorial Dashboard',
        'fields' => [
          'title' => [
            'id' => 'title',
            'table' => 'node_field_data',
            'field' => 'title',
            'label' => 'Title',
            'plugin_id' => 'field',
            'type' => 'string',
            'settings' => ['link_to_entity' => TRUE],
          ],
          'type' => [
            'id' => 'type',
            'table' => 'node_field_data',
            'field' => 'type',
            'label' => 'Type',
            'plugin_id' => 'field',
          ],
          'field_issue' => [
            'id' => 'field_issue',
            'table' => 'node__field_issue',
            'field' => 'field_issue',
            'label' => 'Issue',
            'plugin_id' => 'field',
            'type' => 'entity_reference_label',
          ],
          'moderation_state' => [
            'id' => 'moderation_state',
            'table' => 'node_field_data',
            'field' => 'moderation_state',
            'label' => 'Status',
            'plugin_id' => 'moderation_state_field',
          ],
          'field_homepage_placement' => [
            'id' => 'field_homepage_placement',
            'table' => 'node__field_homepage_placement',
            'field' => 'field_homepage_placement',
            'label' => 'Homepage',
            'plugin_id' => 'field',
            'type' => 'list_default',
          ],
          'field_topics' => [
            'id' => 'field_topics',
            'table' => 'node__field_topics',
            'field' => 'field_topics',
            'label' => 'Topics',
            'plugin_id' => 'field',
            'type' => 'entity_reference_label',
          ],
          'changed' => [
            'id' => 'changed',
            'table' => 'node_field_data',
            'field' => 'changed',
            'label' => 'Last Modified',
            'plugin_id' => 'field',
            'type' => 'timestamp',
            'settings' => ['date_format' => 'short'],
          ],
          'uid' => [
            'id' => 'uid',
            'table' => 'node_field_data',
            'field' => 'uid',
            'label' => 'Author',
            'plugin_id' => 'field',
            'type' => 'entity_reference_label',
          ],
          'operations' => [
            'id' => 'operations',
            'table' => 'node',
            'field' => 'operations',
            'label' => 'Operations',
            'plugin_id' => 'entity_operations',
          ],
        ],
        'filters' => [
          'type' => [
            'id' => 'type',
            'table' => 'node_field_data',
            'field' => 'type',
            'value' => [
              'article' => 'article',
              'in_brief' => 'in_brief',
              'issue' => 'issue',
            ],
            'plugin_id' => 'bundle',
            'group' => 1,
            'exposed' => TRUE,
            'expose' => [
              'operator_id' => 'type_op',
              'label' => 'Content Type',
              'operator' => 'type_op',
              'identifier' => 'type',
            ],
          ],
          'moderation_state' => [
            'id' => 'moderation_state',
            'table' => 'node_field_data',
            'field' => 'moderation_state',
            'plugin_id' => 'moderation_state_filter',
            'exposed' => TRUE,
            'expose' => [
              'label' => 'Status',
              'identifier' => 'status',
            ],
          ],
          'title' => [
            'id' => 'title',
            'table' => 'node_field_data',
            'field' => 'title',
            'operator' => 'contains',
            'plugin_id' => 'string',
            'exposed' => TRUE,
            'expose' => [
              'label' => 'Title',
              'identifier' => 'title',
            ],
          ],
        ],
        'sorts' => [
          'changed' => [
            'id' => 'changed',
            'table' => 'node_field_data',
            'field' => 'changed',
            'order' => 'DESC',
            'plugin_id' => 'date',
          ],
        ],
        'pager' => [
          'type' => 'full',
          'options' => ['items_per_page' => 25],
        ],
        'style' => [
          'type' => 'table',
          'options' => [
            'columns' => [
              'title' => 'title',
              'type' => 'type',
              'field_issue' => 'field_issue',
              'moderation_state' => 'moderation_state',
              'field_homepage_placement' => 'field_homepage_placement',
              'field_topics' => 'field_topics',
              'changed' => 'changed',
              'uid' => 'uid',
              'operations' => 'operations',
            ],
            'default' => 'changed',
            'info' => [
              'title' => ['sortable' => TRUE],
              'type' => ['sortable' => TRUE],
              'field_issue' => ['sortable' => TRUE],
              'moderation_state' => ['sortable' => TRUE],
              'field_homepage_placement' => ['sortable' => FALSE],
              'field_topics' => ['sortable' => FALSE],
              'changed' => ['sortable' => TRUE, 'default_sort_order' => 'desc'],
              'uid' => ['sortable' => TRUE],
              'operations' => ['sortable' => FALSE],
            ],
          ],
        ],
        'row' => [
          'type' => 'fields',
        ],
      ],
    ],
    'page_1' => [
      'id' => 'page_1',
      'display_title' => 'Page',
      'display_plugin' => 'page',
      'position' => 1,
      'display_options' => [
        'path' => 'admin/content/editorial-dashboard',
        'menu' => [
          'type' => 'tab',
          'title' => 'Editorial Dashboard',
          'weight' => 10,
        ],
      ],
    ],
  ],
]);

echo "\n=== All views created successfully ===\n";
