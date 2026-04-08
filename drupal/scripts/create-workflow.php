<?php

/**
 * @file
 * Creates editorial workflow, roles, and Pathauto URL patterns.
 *
 * Run with: ddev drush scr scripts/create-workflow.php
 */

use Drupal\workflows\Entity\Workflow;
use Drupal\user\Entity\Role;
use Drupal\pathauto\Entity\PathautoPattern;

// =========================================================================
// Content Moderation Workflow
// =========================================================================
echo "=== Setting up Editorial Workflow ===\n";

$workflow = Workflow::load('editorial');
if (!$workflow) {
  $workflow = Workflow::create([
    'id' => 'editorial',
    'label' => 'Editorial',
    'type' => 'content_moderation',
  ]);
}

$type_plugin = $workflow->getTypePlugin();

// Ensure states exist.
$states = [
  'draft' => ['label' => 'Draft', 'published' => FALSE, 'default_revision' => FALSE],
  'in_review' => ['label' => 'In Review', 'published' => FALSE, 'default_revision' => FALSE],
  'published' => ['label' => 'Published', 'published' => TRUE, 'default_revision' => TRUE],
  'archived' => ['label' => 'Archived', 'published' => FALSE, 'default_revision' => TRUE],
];

foreach ($states as $state_id => $state_config) {
  if (!$type_plugin->hasState($state_id)) {
    $type_plugin->addState($state_id, $state_config['label']);
  }
}

// Define transitions.
$transitions = [
  'create_new_draft' => ['label' => 'Create New Draft', 'from' => ['draft', 'published', 'archived'], 'to' => 'draft'],
  'submit_for_review' => ['label' => 'Submit for Review', 'from' => ['draft'], 'to' => 'in_review'],
  'publish' => ['label' => 'Publish', 'from' => ['draft', 'in_review'], 'to' => 'published'],
  'archive' => ['label' => 'Archive', 'from' => ['published'], 'to' => 'archived'],
  'send_back' => ['label' => 'Send Back to Draft', 'from' => ['in_review'], 'to' => 'draft'],
];

foreach ($transitions as $transition_id => $transition_config) {
  if (!$type_plugin->hasTransition($transition_id)) {
    $type_plugin->addTransition($transition_id, $transition_config['label'], $transition_config['from'], $transition_config['to']);
  }
}

// Associate content types with workflow.
$type_plugin->addEntityTypeAndBundle('node', 'article');
$type_plugin->addEntityTypeAndBundle('node', 'issue');
$type_plugin->addEntityTypeAndBundle('node', 'in_brief');
$type_plugin->addEntityTypeAndBundle('node', 'page');

$workflow->save();
echo "Editorial workflow created with states: Draft, In Review, Published, Archived\n";

// =========================================================================
// Roles
// =========================================================================
echo "\n=== Creating Roles ===\n";

// Editor role
if (!Role::load('editor')) {
  $editor = Role::create([
    'id' => 'editor',
    'label' => 'Editor',
  ]);
  $editor->grantPermission('access content');
  $editor->grantPermission('access content overview');
  $editor->grantPermission('create article content');
  $editor->grantPermission('edit own article content');
  $editor->grantPermission('edit any article content');
  $editor->grantPermission('create in_brief content');
  $editor->grantPermission('edit own in_brief content');
  $editor->grantPermission('edit any in_brief content');
  $editor->grantPermission('access media overview');
  $editor->grantPermission('create media');
  $editor->grantPermission('update any media');
  $editor->grantPermission('use editorial transition create_new_draft');
  $editor->grantPermission('use editorial transition submit_for_review');
  $editor->grantPermission('use editorial transition publish');
  $editor->grantPermission('view any unpublished content');
  $editor->grantPermission('view latest version');
  $editor->grantPermission('access toolbar');
  $editor->grantPermission('access coffee');
  $editor->save();
  echo "Created role: Editor\n";
}

// Issue Manager role
if (!Role::load('issue_manager')) {
  $manager = Role::create([
    'id' => 'issue_manager',
    'label' => 'Issue Manager',
  ]);
  // Inherit all editor permissions.
  $manager->grantPermission('access content');
  $manager->grantPermission('access content overview');
  $manager->grantPermission('create article content');
  $manager->grantPermission('edit own article content');
  $manager->grantPermission('edit any article content');
  $manager->grantPermission('create in_brief content');
  $manager->grantPermission('edit own in_brief content');
  $manager->grantPermission('edit any in_brief content');
  $manager->grantPermission('access media overview');
  $manager->grantPermission('create media');
  $manager->grantPermission('update any media');
  $manager->grantPermission('use editorial transition create_new_draft');
  $manager->grantPermission('use editorial transition submit_for_review');
  $manager->grantPermission('use editorial transition publish');
  $manager->grantPermission('use editorial transition archive');
  $manager->grantPermission('use editorial transition send_back');
  $manager->grantPermission('view any unpublished content');
  $manager->grantPermission('view latest version');
  $manager->grantPermission('access toolbar');
  $manager->grantPermission('access coffee');
  // Issue-specific permissions.
  $manager->grantPermission('create issue content');
  $manager->grantPermission('edit own issue content');
  $manager->grantPermission('edit any issue content');
  $manager->grantPermission('create page content');
  $manager->grantPermission('edit any page content');
  $manager->grantPermission('administer taxonomy');
  $manager->save();
  echo "Created role: Issue Manager\n";
}

// =========================================================================
// Pathauto URL Patterns
// =========================================================================
echo "\n=== Creating URL Patterns ===\n";

$patterns = [
  [
    'id' => 'article_pattern',
    'label' => 'Article URL Pattern',
    'type' => 'canonical_entities:node',
    'pattern' => '/stories/[node:title]',
    'selection_criteria' => [
      ['id' => 'entity_bundle:node', 'bundles' => ['article' => 'article'], 'negate' => FALSE, 'context_mapping' => ['node' => 'node']],
    ],
  ],
  [
    'id' => 'issue_pattern',
    'label' => 'Issue URL Pattern',
    'type' => 'canonical_entities:node',
    'pattern' => '/issues/[node:field_season]-[node:field_year]',
    'selection_criteria' => [
      ['id' => 'entity_bundle:node', 'bundles' => ['issue' => 'issue'], 'negate' => FALSE, 'context_mapping' => ['node' => 'node']],
    ],
  ],
  [
    'id' => 'in_brief_pattern',
    'label' => 'In Brief URL Pattern',
    'type' => 'canonical_entities:node',
    'pattern' => '/in-brief/[node:title]',
    'selection_criteria' => [
      ['id' => 'entity_bundle:node', 'bundles' => ['in_brief' => 'in_brief'], 'negate' => FALSE, 'context_mapping' => ['node' => 'node']],
    ],
  ],
];

foreach ($patterns as $pattern_config) {
  if (!PathautoPattern::load($pattern_config['id'])) {
    $pattern = PathautoPattern::create([
      'id' => $pattern_config['id'],
      'label' => $pattern_config['label'],
      'type' => $pattern_config['type'],
      'pattern' => $pattern_config['pattern'],
      'weight' => 0,
    ]);

    foreach ($pattern_config['selection_criteria'] as $condition) {
      $pattern->addSelectionCondition($condition);
    }

    $pattern->save();
    echo "Created URL pattern: {$pattern_config['label']} -> {$pattern_config['pattern']}\n";
  }
}

echo "\n=== Workflow, roles, and URL patterns created successfully ===\n";
