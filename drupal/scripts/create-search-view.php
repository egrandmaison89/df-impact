<?php
/**
 * Create a Search API Views display at /search.
 */

use Drupal\views\Entity\View;

$view_id = 'df_impact_search';
$existing = View::load($view_id);

if ($existing) {
  echo "View '$view_id' already exists.\n";
  exit;
}

$view = View::create([
  'id' => $view_id,
  'label' => 'Search',
  'description' => 'Site-wide full-text search using Search API.',
  'module' => 'views',
  'status' => TRUE,
  'base_table' => 'search_api_index_df_impact_content',
  'base_field' => 'search_api_id',
  'display' => [
    'default' => [
      'id' => 'default',
      'display_title' => 'Default',
      'display_plugin' => 'default',
      'position' => 0,
      'display_options' => [
        'title' => 'Search',
        'pager' => [
          'type' => 'full',
          'options' => [
            'items_per_page' => 10,
            'offset' => 0,
            'id' => 0,
            'total_pages' => NULL,
            'expose' => [
              'items_per_page' => FALSE,
              'items_per_page_label' => 'Items per page',
              'items_per_page_options' => '5, 10, 20, 40, 60',
              'items_per_page_options_all' => FALSE,
              'items_per_page_options_all_label' => '- All -',
              'offset' => FALSE,
              'offset_label' => 'Offset',
            ],
            'quantity' => 9,
          ],
        ],
        'style' => [
          'type' => 'default',
          'options' => [
            'grouping' => [],
            'row_class' => '',
            'default_row_class' => TRUE,
          ],
        ],
        'row' => [
          'type' => 'search_api',
          'options' => [
            'view_mode' => 'teaser',
          ],
        ],
        'fields' => [],
        'filters' => [
          'search_api_fulltext' => [
            'id' => 'search_api_fulltext',
            'table' => 'search_api_index_df_impact_content',
            'field' => 'search_api_fulltext',
            'relationship' => 'none',
            'group_type' => 'group',
            'admin_label' => '',
            'operator' => '=',
            'value' => '',
            'group' => 1,
            'exposed' => TRUE,
            'expose' => [
              'operator_id' => '',
              'label' => 'Search',
              'description' => '',
              'use_operator' => FALSE,
              'operator' => '',
              'operator_limit_selection' => FALSE,
              'operator_list' => [],
              'identifier' => 'search_api_fulltext',
              'required' => FALSE,
              'remember' => FALSE,
              'multiple' => FALSE,
              'remember_roles' => [
                'authenticated' => 'authenticated',
              ],
              'placeholder' => 'Search articles, research, topics...',
            ],
            'is_grouped' => FALSE,
            'group_info' => [
              'label' => '',
              'description' => '',
              'identifier' => '',
              'optional' => TRUE,
              'widget' => 'select',
              'multiple' => FALSE,
              'remember' => FALSE,
              'default_group' => 'All',
              'default_group_multiple' => [],
              'group_items' => [],
            ],
            'plugin_id' => 'search_api_fulltext',
          ],
          'status' => [
            'id' => 'status',
            'table' => 'search_api_index_df_impact_content',
            'field' => 'status',
            'relationship' => 'none',
            'group_type' => 'group',
            'admin_label' => '',
            'operator' => '=',
            'value' => '1',
            'group' => 1,
            'exposed' => FALSE,
            'expose' => [
              'operator_id' => '',
              'label' => '',
              'description' => '',
              'use_operator' => FALSE,
              'operator' => '',
              'operator_limit_selection' => FALSE,
              'operator_list' => [],
              'identifier' => '',
              'required' => FALSE,
              'remember' => FALSE,
              'multiple' => FALSE,
              'remember_roles' => [
                'authenticated' => 'authenticated',
              ],
            ],
            'is_grouped' => FALSE,
            'group_info' => [
              'label' => '',
              'description' => '',
              'identifier' => '',
              'optional' => TRUE,
              'widget' => 'select',
              'multiple' => FALSE,
              'remember' => FALSE,
              'default_group' => 'All',
              'default_group_multiple' => [],
              'group_items' => [],
            ],
            'plugin_id' => 'search_api_status',
          ],
        ],
        'sorts' => [
          'search_api_relevance' => [
            'id' => 'search_api_relevance',
            'table' => 'search_api_index_df_impact_content',
            'field' => 'search_api_relevance',
            'relationship' => 'none',
            'group_type' => 'group',
            'admin_label' => '',
            'order' => 'DESC',
            'expose' => [
              'label' => '',
            ],
            'exposed' => FALSE,
            'plugin_id' => 'search_api_relevance',
          ],
        ],
        'header' => [],
        'footer' => [],
        'empty' => [
          'area_text_custom' => [
            'id' => 'area_text_custom',
            'table' => 'views',
            'field' => 'area_text_custom',
            'relationship' => 'none',
            'group_type' => 'group',
            'admin_label' => '',
            'empty' => TRUE,
            'tokenize' => FALSE,
            'content' => 'No results found. Please try a different search term.',
            'plugin_id' => 'text_custom',
          ],
        ],
        'relationships' => [],
        'arguments' => [],
        'display_extenders' => [],
        'cache' => [
          'type' => 'search_api_none',
          'options' => [],
        ],
        'exposed_form' => [
          'type' => 'basic',
          'options' => [
            'submit_button' => 'Search',
            'reset_button' => FALSE,
            'reset_button_label' => 'Reset',
            'exposed_sorts_label' => 'Sort by',
            'expose_sort_order' => TRUE,
            'sort_asc_label' => 'Asc',
            'sort_desc_label' => 'Desc',
          ],
        ],
        'use_more' => FALSE,
        'use_more_always' => TRUE,
        'use_more_text' => 'more',
        'link_display' => '',
        'link_url' => '',
        'query' => [
          'type' => 'views_query',
          'options' => [
            'bypass_access' => FALSE,
            'skip_access' => FALSE,
          ],
        ],
        'rendering_language' => '***LANGUAGE_language_interface***',
        'field_langcode' => '***LANGUAGE_language_interface***',
        'field_langcode_add_to_query' => TRUE,
      ],
      'cache_metadata' => [
        'max-age' => -1,
        'contexts' => [
          'languages:language_interface',
          'url',
          'url.query_args',
        ],
        'tags' => [],
      ],
    ],
    'page_1' => [
      'id' => 'page_1',
      'display_title' => 'Search Page',
      'display_plugin' => 'page',
      'position' => 1,
      'display_options' => [
        'path' => 'search',
        'menu' => [
          'type' => 'none',
          'title' => '',
          'description' => '',
          'expanded' => FALSE,
          'parent' => '',
          'weight' => 0,
          'context' => '',
          'menu_name' => 'main',
        ],
        'display_extenders' => [],
      ],
      'cache_metadata' => [
        'max-age' => -1,
        'contexts' => [
          'languages:language_interface',
          'url',
          'url.query_args',
        ],
        'tags' => [],
      ],
    ],
  ],
]);

$view->save();
echo "View '$view_id' created successfully.\n";
echo "Search page available at: /search\n";
