<?php

/**
 * @file
 * Creates sample content to test the theme.
 *
 * Run with: ddev drush scr scripts/create-sample-content.php
 */

use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

// Find taxonomy terms for tagging.
$topic_terms = \Drupal::entityTypeManager()
  ->getStorage('taxonomy_term')
  ->loadByProperties(['vid' => 'topics']);

$cancer_terms = \Drupal::entityTypeManager()
  ->getStorage('taxonomy_term')
  ->loadByProperties(['vid' => 'cancer_types']);

$content_type_terms = \Drupal::entityTypeManager()
  ->getStorage('taxonomy_term')
  ->loadByProperties(['vid' => 'content_type']);

// Get term IDs by name.
$topic_ids = [];
foreach ($topic_terms as $term) {
  $topic_ids[$term->getName()] = $term->id();
}
$cancer_ids = [];
foreach ($cancer_terms as $term) {
  $cancer_ids[$term->getName()] = $term->id();
}
$ct_ids = [];
foreach ($content_type_terms as $term) {
  $ct_ids[$term->getName()] = $term->id();
}

// =========================================================================
// Create an Issue
// =========================================================================
echo "=== Creating Sample Issue ===\n";
$issue = Node::create([
  'type' => 'issue',
  'title' => 'Spring 2026',
  'field_season' => 'spring',
  'field_year' => 2026,
  'field_description' => [
    'value' => '<p>Welcome to the Spring 2026 edition of Impact Magazine, featuring stories of extraordinary generosity and groundbreaking research at Dana-Farber Cancer Institute.</p>',
    'format' => 'full_html',
  ],
  'moderation_state' => 'published',
]);
$issue->save();
echo "Created issue: Spring 2026 (nid: {$issue->id()})\n";

// =========================================================================
// Create Articles
// =========================================================================
echo "\n=== Creating Sample Articles ===\n";

$articles = [
  [
    'title' => 'A Legacy of Leadership and Hope: The Saverin Foundation\'s Commitment to Patients with Metastatic Breast Cancer',
    'field_subtitle' => 'How one family\'s generosity is transforming treatment options for patients facing the most challenging diagnoses.',
    'field_byline' => 'Amber Sinicrope',
    'field_photo_credit' => 'Bryce Vickmark',
    'body' => '<p>When the Elaine and Eduardo Saverin Foundation made its landmark commitment to Dana-Farber Cancer Institute, it marked a pivotal moment in the fight against metastatic breast cancer. The gift, one of the largest ever directed toward this specific area of research, will fund a comprehensive program aimed at developing next-generation treatments.</p><p>"We believe deeply in the power of science to change lives," said Eduardo Saverin, co-founder of the foundation. "Dana-Farber represents the best of what\'s possible when brilliant minds are given the resources they need."</p><p>The funding will support the work of Sara Tolaney, MD, MPH, chief of the Division of Breast Oncology, and her team as they pursue innovative clinical trials and translational research projects. Dr. Tolaney\'s program has already achieved remarkable breakthroughs in understanding the biology of treatment-resistant breast cancers.</p><p>"This gift allows us to think bigger and move faster," Dr. Tolaney explained. "We can now pursue studies that were previously beyond our reach, combining novel drug combinations with cutting-edge diagnostic tools to give patients more options and more hope."</p>',
    'field_topics' => [$topic_ids['Immunotherapy'] ?? NULL, $topic_ids['Drug Development'] ?? NULL],
    'field_cancer_types' => [$cancer_ids['Breast Cancer'] ?? NULL],
    'field_content_type' => $ct_ids['Feature Story'] ?? NULL,
    'field_homepage_placement' => 'featured',
    'field_excerpt' => 'The Elaine and Eduardo Saverin Foundation\'s landmark commitment to Dana-Farber will fund next-generation treatments for metastatic breast cancer.',
  ],
  [
    'title' => 'Jimmy Fund Walk Steps Into Record-Breaking Year with Over $11.2 Million Raised',
    'field_subtitle' => 'Thousands of walkers unite for cancer research in the event\'s most successful year yet.',
    'field_byline' => 'Hannah White',
    'field_photo_credit' => 'Sam Ogden',
    'body' => '<p>The Jimmy Fund Walk celebrated a historic milestone this year, raising more than $11.2 million for Dana-Farber Cancer Institute — the most in the event\'s storied history. More than 8,000 walkers participated, traversing the iconic Boston Marathon route from Hopkinton to the Copley Square finish line.</p><p>"Simply said, the money raised offers a chance at life," shared Barbara Gubb, a 15-year walk veteran who walks in memory of her late husband. "Every step we take brings us closer to a world without cancer."</p><p>The event has now raised more than $170 million for Dana-Farber since its inception, making it one of the most successful peer-to-peer fundraising events in the country. Walkers of all ages and abilities participated, from seasoned marathoners to families pushing strollers.</p>',
    'field_topics' => [$topic_ids['Grassroots Support'] ?? NULL],
    'field_cancer_types' => [],
    'field_content_type' => $ct_ids['Event Recap'] ?? NULL,
    'field_homepage_placement' => 'featured',
    'field_excerpt' => 'More than 8,000 walkers raised a record $11.2 million along the Boston Marathon route, bringing the event\'s all-time total past $170 million.',
  ],
  [
    'title' => 'Pan-Mass Challenge Riders Cross $1 Billion Milestone',
    'field_subtitle' => '',
    'field_byline' => 'Tarice Gray',
    'field_photo_credit' => '',
    'body' => '<p>In a moment decades in the making, the Pan-Mass Challenge announced that its lifetime fundraising total has surpassed the extraordinary $1 billion mark. The announcement, made at a celebration event at Fenway Park, was met with thunderous applause from riders, volunteers, and supporters.</p><p>The PMC, which was founded in 1980 by Billy Starr, has grown from 36 riders raising $10,200 to more than 6,800 riders raising over $72 million in a single year. It is the single largest athletic fundraising event in the country.</p>',
    'field_topics' => [$topic_ids['Grassroots Support'] ?? NULL],
    'field_cancer_types' => [],
    'field_content_type' => $ct_ids['Event Recap'] ?? NULL,
    'field_homepage_placement' => 'recent_highlights',
    'field_excerpt' => 'The Pan-Mass Challenge reaches an incredible $1 billion in lifetime fundraising for Dana-Farber Cancer Institute.',
  ],
  [
    'title' => 'What Is Epigenetics? Understanding the Science Behind Gene Expression',
    'field_subtitle' => 'How researchers are learning to read — and rewrite — the instructions that control our genes.',
    'field_byline' => 'Amber Sinicrope',
    'field_photo_credit' => 'Bryce Vickmark',
    'body' => '<p>Every cell in your body contains the same DNA. Yet a heart cell looks and behaves nothing like a brain cell. The difference lies not in the genetic code itself, but in how that code is read — a field of study known as epigenetics.</p><p>At Dana-Farber, researchers are at the forefront of understanding how epigenetic changes contribute to cancer development and, more importantly, how those changes can be reversed to treat the disease.</p><p>"Cancer isn\'t just about mutated genes," explains Dr. Mark Dawson, a leading epigenetics researcher. "It\'s also about genes that are turned on or off at the wrong time. If we can learn to control that switch, we have a powerful new avenue for treatment."</p>',
    'field_topics' => [$topic_ids['Epigenetics'] ?? NULL, $topic_ids['Basic Science'] ?? NULL],
    'field_cancer_types' => [],
    'field_content_type' => $ct_ids['Feature Story'] ?? NULL,
    'field_homepage_placement' => 'recent_highlights',
    'field_excerpt' => 'Dana-Farber researchers are pioneering new treatments based on understanding how genes are turned on and off in cancer cells.',
  ],
  [
    'title' => 'Your Generosity on GivingTuesday Is Fueling the Next Victory Over Cancer',
    'field_subtitle' => '',
    'field_byline' => 'Hannah White',
    'field_photo_credit' => '',
    'body' => '<p>On GivingTuesday, supporters from across the country came together to make a difference in the fight against cancer. The outpouring of generosity provided flexible, unrestricted funding that supports Dana-Farber\'s investment in transformational research.</p><p>Every gift, regardless of size, contributes to the institute\'s mission of providing expert, compassionate care to patients while advancing the understanding of cancer through basic and clinical research.</p>',
    'field_topics' => [$topic_ids['Essential Opportunities'] ?? NULL],
    'field_cancer_types' => [],
    'field_content_type' => $ct_ids['Digital Exclusive'] ?? NULL,
    'field_homepage_placement' => 'none',
    'field_excerpt' => 'GivingTuesday supporters provide vital unrestricted funding for Dana-Farber\'s most pressing research priorities.',
  ],
  [
    'title' => 'Pediatric Brain Tumor Foundation Accelerates Research at Dana-Farber',
    'field_subtitle' => 'New grant supports innovative approaches to treating the most common solid tumors in children.',
    'field_byline' => 'Tarice Gray',
    'field_photo_credit' => 'Sam Ogden',
    'body' => '<p>The Pediatric Brain Tumor Foundation has awarded a significant grant to researchers at Dana-Farber Cancer Institute, funding a new initiative aimed at developing targeted therapies for the most aggressive forms of pediatric brain tumors.</p><p>Pediatric brain tumors are the leading cause of cancer-related death in children, and current treatment options often come with devastating long-term side effects. The new funding will support research into precision medicine approaches that aim to be both more effective and less harmful.</p>',
    'field_topics' => [$topic_ids['Discovery Science'] ?? NULL],
    'field_cancer_types' => [$cancer_ids['Brain Tumors'] ?? NULL, $cancer_ids['Pediatric Cancers'] ?? NULL],
    'field_content_type' => $ct_ids['Donor Profile'] ?? NULL,
    'field_homepage_placement' => 'recent_highlights',
    'field_excerpt' => 'A major new grant funds precision medicine approaches for the most aggressive pediatric brain tumors.',
  ],
];

foreach ($articles as $article_data) {
  $topics_refs = array_filter(array_map(function($tid) {
    return $tid ? ['target_id' => $tid] : NULL;
  }, $article_data['field_topics']));

  $cancer_refs = array_filter(array_map(function($tid) {
    return $tid ? ['target_id' => $tid] : NULL;
  }, $article_data['field_cancer_types']));

  $node = Node::create([
    'type' => 'article',
    'title' => $article_data['title'],
    'field_subtitle' => $article_data['field_subtitle'],
    'field_byline' => $article_data['field_byline'],
    'field_photo_credit' => $article_data['field_photo_credit'],
    'body' => [
      'value' => $article_data['body'],
      'format' => 'full_html',
    ],
    'field_issue' => ['target_id' => $issue->id()],
    'field_topics' => array_values($topics_refs),
    'field_cancer_types' => array_values($cancer_refs),
    'field_content_type' => $article_data['field_content_type'] ? ['target_id' => $article_data['field_content_type']] : [],
    'field_homepage_placement' => $article_data['field_homepage_placement'],
    'field_excerpt' => [
      'value' => $article_data['field_excerpt'],
      'format' => 'basic_html',
    ],
    'moderation_state' => 'published',
  ]);
  $node->save();
  echo "Created article: {$article_data['title']} (nid: {$node->id()})\n";
}

// =========================================================================
// Create In Brief Items
// =========================================================================
echo "\n=== Creating Sample In Brief Items ===\n";

$briefs = [
  [
    'title' => 'PMC Winter Cycle Raises $3.2M',
    'summary' => 'The Pan-Mass Challenge Winter Cycle at Fenway Park raised $3.2 million for Dana-Farber, with over 1,000 riders participating in the indoor cycling event.',
  ],
  [
    'title' => 'New Clinical Trial for Rare Blood Cancers',
    'summary' => 'Dana-Farber has opened a first-in-human clinical trial for a novel targeted therapy designed to treat rare subtypes of blood cancers that currently have limited treatment options.',
  ],
  [
    'title' => 'Impact Magazine Wins National Award',
    'summary' => 'Impact Magazine received a Gold Award from the Council for Advancement and Support of Education (CASE) for excellence in philanthropic communications.',
  ],
];

foreach ($briefs as $brief_data) {
  $node = Node::create([
    'type' => 'in_brief',
    'title' => $brief_data['title'],
    'field_summary' => [
      'value' => '<p>' . $brief_data['summary'] . '</p>',
      'format' => 'basic_html',
    ],
    'field_issue' => ['target_id' => $issue->id()],
    'moderation_state' => 'published',
  ]);
  $node->save();
  echo "Created In Brief: {$brief_data['title']} (nid: {$node->id()})\n";
}

echo "\n=== All sample content created successfully ===\n";
echo "Visit the site at: http://df-impact.ddev.site\n";
