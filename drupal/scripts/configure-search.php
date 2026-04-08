#!/usr/bin/env php
<?php

/**
 * @file
 * Configure Search API with Database backend for the Dana-Farber Impact site.
 *
 * Sets up:
 * - Search server (Database backend)
 * - Search index for Articles, Issues, In Brief
 * - Fields indexed: title, body, topics, cancer_types, byline, subtitle, excerpt
 */

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Item\Field;

// Create the search server (Database backend)
$server_id = 'df_impact_search';
$server = Server::load($server_id);

if (!$server) {
  $server = Server::create([
    'id' => $server_id,
    'name' => 'Dana-Farber Impact Search',
    'status' => TRUE,
    'description' => 'Full-text search for the Impact Magazine site using the Database backend.',
    'backend' => 'search_api_db',
    'backend_config' => [
      'min_chars' => 3,
      'partial_matches' => FALSE,
      'matching' => 'words',
    ],
  ]);
  $server->save();
  echo "✅ Search server created: $server_id\n";
} else {
  echo "ℹ️  Search server already exists: $server_id\n";
}

// Create the search index
$index_id = 'df_impact_content';
$index = Index::load($index_id);

if (!$index) {
  $index = Index::create([
    'id' => $index_id,
    'name' => 'Dana-Farber Impact Content',
    'status' => TRUE,
    'description' => 'Index for articles, issues, and in-brief content.',
    'server' => $server_id,
    'datasource_settings' => [
      'entity:node' => [
        'plugin_id' => 'entity:node',
        'settings' => [
          'bundles' => [
            'default' => FALSE,
            'selected' => ['article', 'issue', 'in_brief'],
          ],
          'languages' => [
            'default' => TRUE,
            'selected' => [],
          ],
        ],
      ],
    ],
    'tracker_settings' => [
      'default' => [
        'plugin_id' => 'default',
        'settings' => [],
      ],
    ],
    'processor_settings' => [
      'aggregated_field' => [
        'plugin_id' => 'aggregated_field',
        'weights' => [],
        'settings' => [],
      ],
      'html_filter' => [
        'plugin_id' => 'html_filter',
        'weights' => ['preprocessor' => -15],
        'settings' => [
          'title' => FALSE,
          'alt' => TRUE,
          'tags' => ['h1' => 5, 'h2' => 3, 'h3' => 2, 'strong' => 2, 'b' => 2],
        ],
      ],
      'ignorecase' => [
        'plugin_id' => 'ignorecase',
        'weights' => ['postprocessor' => 0],
        'settings' => [],
      ],
      'stopwords' => [
        'plugin_id' => 'stopwords',
        'weights' => ['postprocessor' => 0],
        'settings' => ['stopwords' => 'a an are as at be but by for if in into is it no not of on or such that the their then there these they this to was will with'],
      ],
      'tokenizer' => [
        'plugin_id' => 'tokenizer',
        'weights' => ['postprocessor' => 0],
        'settings' => ['spaces' => '', 'ignored' => ''],
      ],
    ],
    'field_settings' => [
      // Title - most important
      'title' => [
        'label' => 'Title',
        'datasource_id' => 'entity:node',
        'property_path' => 'title',
        'type' => 'text',
        'boost' => '5.0',
        'indexed_locked' => FALSE,
        'type_locked' => FALSE,
        'dependencies' => [],
      ],
      // Body
      'body' => [
        'label' => 'Body',
        'datasource_id' => 'entity:node',
        'property_path' => 'body',
        'type' => 'text',
        'boost' => '1.0',
        'indexed_locked' => FALSE,
        'type_locked' => FALSE,
        'dependencies' => [],
      ],
      // Excerpt / Subtitle
      'field_excerpt' => [
        'label' => 'Excerpt',
        'datasource_id' => 'entity:node',
        'property_path' => 'field_excerpt',
        'type' => 'text',
        'boost' => '2.0',
        'indexed_locked' => FALSE,
        'type_locked' => FALSE,
        'dependencies' => [],
      ],
      'field_subtitle' => [
        'label' => 'Subtitle',
        'datasource_id' => 'entity:node',
        'property_path' => 'field_subtitle',
        'type' => 'text',
        'boost' => '3.0',
        'indexed_locked' => FALSE,
        'type_locked' => FALSE,
        'dependencies' => [],
      ],
      // Byline
      'field_byline' => [
        'label' => 'Byline',
        'datasource_id' => 'entity:node',
        'property_path' => 'field_byline',
        'type' => 'text',
        'boost' => '1.0',
        'indexed_locked' => FALSE,
        'type_locked' => FALSE,
        'dependencies' => [],
      ],
      // Taxonomy fields (for faceting)
      'field_topics' => [
        'label' => 'Topics',
        'datasource_id' => 'entity:node',
        'property_path' => 'field_topics',
        'type' => 'integer',
        'boost' => '1.0',
        'indexed_locked' => FALSE,
        'type_locked' => FALSE,
        'dependencies' => [],
      ],
      'field_cancer_types' => [
        'label' => 'Cancer Types',
        'datasource_id' => 'entity:node',
        'property_path' => 'field_cancer_types',
        'type' => 'integer',
        'boost' => '1.0',
        'indexed_locked' => FALSE,
        'type_locked' => FALSE,
        'dependencies' => [],
      ],
      // Node type (for filtering)
      'type' => [
        'label' => 'Content type',
        'datasource_id' => 'entity:node',
        'property_path' => 'type',
        'type' => 'string',
        'boost' => '1.0',
        'indexed_locked' => FALSE,
        'type_locked' => FALSE,
        'dependencies' => [],
      ],
      // Status
      'status' => [
        'label' => 'Published',
        'datasource_id' => 'entity:node',
        'property_path' => 'status',
        'type' => 'boolean',
        'boost' => '1.0',
        'indexed_locked' => FALSE,
        'type_locked' => FALSE,
        'dependencies' => [],
      ],
      // Created date
      'created' => [
        'label' => 'Created date',
        'datasource_id' => 'entity:node',
        'property_path' => 'created',
        'type' => 'date',
        'boost' => '1.0',
        'indexed_locked' => FALSE,
        'type_locked' => FALSE,
        'dependencies' => [],
      ],
    ],
    'options' => [
      'index_directly' => FALSE,
      'cron_limit' => 50,
    ],
  ]);
  $index->save();
  echo "✅ Search index created: $index_id\n";
} else {
  echo "ℹ️  Search index already exists: $index_id\n";
}

// Index all content
echo "\nIndexing all content (this may take a moment)...\n";
try {
  $index = Index::load($index_id);
  $index->indexItems();
  echo "✅ Content queued for indexing.\n";
} catch (\Exception $e) {
  echo "⚠️  Indexing error: " . $e->getMessage() . "\n";
}

// Trigger the index run via cron-like batch
try {
  $result = $index->getServerInstance()->getBackend()->indexItems($index, []);
  echo "✅ Items indexed: " . count($result) . "\n";
} catch (\Exception $e) {
  // This is expected if there's nothing to index
  echo "ℹ️  " . $e->getMessage() . "\n";
}

echo "\n🎉 Search configuration complete!\n";
echo "Next: Create a Search API Views display at /search\n";
