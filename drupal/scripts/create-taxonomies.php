<?php

/**
 * @file
 * Creates taxonomy vocabularies and terms for Dana-Farber Impact Magazine.
 *
 * Run with: ddev drush scr scripts/create-taxonomies.php
 */

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;

// --- Vocabulary: Topics (hierarchical) ---
$vocab = Vocabulary::load('topics');
if (!$vocab) {
  $vocab = Vocabulary::create([
    'vid' => 'topics',
    'name' => 'Topics',
    'description' => 'Editorial topics for categorizing articles.',
  ]);
  $vocab->save();
  echo "Created vocabulary: Topics\n";
}

$topics_hierarchy = [
  'Research' => [
    'Basic Science',
    'Discovery Science',
    'Drug Development',
    'Immunotherapy',
    'Epigenetics',
    'AI and Machine Learning',
  ],
  'Patient Care' => [
    'Access and Equity',
    'Exceptional Expertise',
  ],
  'Philanthropy' => [
    'Grassroots Support',
    'Essential Opportunities',
    'Campaign Updates',
  ],
];

foreach ($topics_hierarchy as $parent_name => $children) {
  $parent = Term::create([
    'vid' => 'topics',
    'name' => $parent_name,
  ]);
  $parent->save();
  echo "  Created parent term: $parent_name\n";

  foreach ($children as $child_name) {
    $child = Term::create([
      'vid' => 'topics',
      'name' => $child_name,
      'parent' => ['target_id' => $parent->id()],
    ]);
    $child->save();
    echo "    Created child term: $child_name\n";
  }
}

// --- Vocabulary: Cancer Types (flat) ---
$vocab = Vocabulary::load('cancer_types');
if (!$vocab) {
  $vocab = Vocabulary::create([
    'vid' => 'cancer_types',
    'name' => 'Cancer Types',
    'description' => 'Types of cancer for article tagging.',
  ]);
  $vocab->save();
  echo "Created vocabulary: Cancer Types\n";
}

$cancer_types = [
  'Breast Cancer',
  'Lung Cancer',
  'Pediatric Cancers',
  'Blood Cancers',
  'Gastrointestinal Cancers',
  'Brain Tumors',
  'Ovarian Cancer',
  'Prostate Cancer',
  'Colorectal Cancer',
  'Multiple Myeloma',
];

foreach ($cancer_types as $type) {
  $term = Term::create([
    'vid' => 'cancer_types',
    'name' => $type,
  ]);
  $term->save();
  echo "  Created term: $type\n";
}

// --- Vocabulary: Content Type (flat) ---
$vocab = Vocabulary::load('content_type');
if (!$vocab) {
  $vocab = Vocabulary::create([
    'vid' => 'content_type',
    'name' => 'Content Type',
    'description' => 'Classifies the format or purpose of an article.',
  ]);
  $vocab->save();
  echo "Created vocabulary: Content Type\n";
}

$content_types = [
  'Digital Exclusive',
  'Campaign Update',
  'Leadership Message',
  'Feature Story',
  'Donor Profile',
  'Event Recap',
];

foreach ($content_types as $type) {
  $term = Term::create([
    'vid' => 'content_type',
    'name' => $type,
  ]);
  $term->save();
  echo "  Created term: $type\n";
}

echo "\nAll taxonomies and terms created successfully.\n";
