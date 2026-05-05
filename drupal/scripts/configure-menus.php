<?php

/**
 * @file
 * Creates menu structure for Dana-Farber Impact Magazine.
 *
 * Run with: ddev drush scr scripts/configure-menus.php
 */

use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\NodeInterface;
use Drupal\system\Entity\Menu;

// =========================================================================
// Create Footer Menu
// =========================================================================
$footer_menu = Menu::load('footer');
if (!$footer_menu) {
  $footer_menu = Menu::create([
    'id' => 'footer',
    'label' => 'Footer',
    'description' => 'Footer navigation links.',
  ]);
  $footer_menu->save();
  echo "Created menu: Footer\n";
}

// =========================================================================
// Main Navigation Links
// =========================================================================
echo "\n=== Creating Main Navigation Links ===\n";

// Clear existing main menu links.
$storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
$existing_links = $storage->loadByProperties(['menu_name' => 'main']);
foreach ($existing_links as $link) {
  $link->delete();
}
echo "Cleared existing main menu links.\n";

$season_rank = [
  'winter' => 4,
  'fall' => 3,
  'summer' => 2,
  'spring' => 1,
];

// --- Issues (parent + children), WordPress-style flyout ---
$issues_parent = MenuLinkContent::create([
  'title' => 'Issues',
  'link' => ['uri' => 'route:<nolink>'],
  'menu_name' => 'main',
  'weight' => 0,
  'expanded' => TRUE,
]);
$issues_parent->save();
echo "Created menu link: Issues (parent)\n";

$browse_all = MenuLinkContent::create([
  'title' => 'Browse all issues',
  'link' => ['uri' => 'internal:/issues'],
  'menu_name' => 'main',
  'parent' => 'menu_link_content:' . $issues_parent->uuid(),
  'weight' => -50,
  'expanded' => FALSE,
]);
$browse_all->save();
echo "  Created child: Browse all issues\n";

$issue_nodes = \Drupal::entityTypeManager()
  ->getStorage('node')
  ->loadByProperties(['type' => 'issue', 'status' => 1]);

$issues_sorted = array_values(array_filter($issue_nodes, function ($node) {
  return $node instanceof NodeInterface && $node->isPublished();
}));

usort($issues_sorted, function (NodeInterface $a, NodeInterface $b) use ($season_rank) {
  $ya = (int) ($a->get('field_year')->value ?? 0);
  $yb = (int) ($b->get('field_year')->value ?? 0);
  if ($ya !== $yb) {
    return $yb <=> $ya;
  }
  $sa = $season_rank[$a->get('field_season')->value] ?? 0;
  $sb = $season_rank[$b->get('field_season')->value] ?? 0;
  return $sb <=> $sa;
});

$issue_weight = 0;
foreach ($issues_sorted as $issue) {
  $child = MenuLinkContent::create([
    'title' => $issue->label(),
    'link' => ['uri' => 'entity:node/' . $issue->id()],
    'menu_name' => 'main',
    'parent' => 'menu_link_content:' . $issues_parent->uuid(),
    'weight' => $issue_weight++,
    'expanded' => FALSE,
  ]);
  $child->save();
  echo '  Created issue link: ' . $issue->label() . "\n";
}

$main_links = [
  [
    'title' => 'In Brief',
    'link' => ['uri' => 'internal:/in-brief'],
    'menu_name' => 'main',
    'weight' => 2,
    'expanded' => FALSE,
  ],
  [
    'title' => 'About',
    'link' => ['uri' => 'internal:/about-impact'],
    'menu_name' => 'main',
    'weight' => 3,
    'expanded' => FALSE,
  ],
];

// Topics parent + children.
$topics_parent = MenuLinkContent::create([
  'title' => 'Topics',
  'link' => ['uri' => 'route:<nolink>'],
  'menu_name' => 'main',
  'weight' => 4,
  'expanded' => TRUE,
]);
$topics_parent->save();
echo "Created menu link: Topics (parent)\n";

$topics = \Drupal::entityTypeManager()
  ->getStorage('taxonomy_term')
  ->loadByProperties(['vid' => 'topics', 'parent' => 0]);

$topic_weight = 0;
foreach ($topics as $term) {
  $slug = function_exists('df_setup_topic_category_slug')
    ? df_setup_topic_category_slug($term->getName())
    : strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($term->getName())));
  $child = MenuLinkContent::create([
    'title' => $term->getName(),
    'link' => ['uri' => 'internal:/category/' . $slug],
    'menu_name' => 'main',
    'parent' => 'menu_link_content:' . $topics_parent->uuid(),
    'weight' => $topic_weight++,
    'expanded' => FALSE,
  ]);
  $child->save();
  echo "  Created topic link: {$term->getName()}\n";
}

foreach ($main_links as $link_data) {
  $link = MenuLinkContent::create($link_data);
  $link->save();
  echo "Created menu link: {$link_data['title']}\n";
}

// Donate (skipped in theme menu template when Jimmy Fund donation URL).
$donate = MenuLinkContent::create([
  'title' => 'Donate',
  'link' => ['uri' => 'https://danafarber.jimmyfund.org/site/Donation2?df_id=2101&mfc_pref=T&2101.donation=form1&utm_source=dfimpact&utm_medium=button&utm_campaign=AGDFI031323&s_src=AGDFI031323&s_subsrc=AGDFI031323'],
  'menu_name' => 'main',
  'weight' => 10,
  'expanded' => FALSE,
]);
$donate->save();
echo "Created menu link: Donate (external)\n";

// =========================================================================
// Footer Links
// =========================================================================
echo "\n=== Creating Footer Links ===\n";

$existing_footer = $storage->loadByProperties(['menu_name' => 'footer']);
foreach ($existing_footer as $link) {
  $link->delete();
}
echo "Cleared existing footer menu links.\n";

$footer_links = [
  ['title' => 'Dana-Farber Cancer Institute', 'link' => ['uri' => 'https://www.dana-farber.org'], 'weight' => 0],
  ['title' => 'The Jimmy Fund', 'link' => ['uri' => 'https://www.jimmyfund.org'], 'weight' => 1],
  ['title' => 'About Impact', 'link' => ['uri' => 'internal:/about-impact'], 'weight' => 2],
  ['title' => 'Privacy Policy', 'link' => ['uri' => 'https://www.dana-farber.org/privacy-statement'], 'weight' => 3],
  ['title' => 'Contact Us', 'link' => ['uri' => 'mailto:Tarice_Gray@dfci.harvard.edu'], 'weight' => 4],
];

foreach ($footer_links as $link_data) {
  $link_data['menu_name'] = 'footer';
  $link = MenuLinkContent::create($link_data);
  $link->save();
  echo "Created footer link: {$link_data['title']}\n";
}

echo "\n=== Menu configuration complete ===\n";
