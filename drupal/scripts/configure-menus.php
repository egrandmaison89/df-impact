<?php

/**
 * @file
 * Creates menu structure for Dana-Farber Impact Magazine.
 *
 * Run with: ddev drush scr scripts/configure-menus.php
 */

use Drupal\menu_link_content\Entity\MenuLinkContent;
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

$main_links = [
  [
    'title' => 'Current Issue',
    'link' => ['uri' => 'internal:/issues'],
    'menu_name' => 'main',
    'weight' => 0,
    'expanded' => FALSE,
  ],
  [
    'title' => 'Browse Issues',
    'link' => ['uri' => 'internal:/issues'],
    'menu_name' => 'main',
    'weight' => 1,
    'expanded' => FALSE,
  ],
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

// Create Topics parent first.
$topics_parent = MenuLinkContent::create([
  'title' => 'Topics',
  'link' => ['uri' => 'route:<nolink>'],
  'menu_name' => 'main',
  'weight' => 4,
  'expanded' => TRUE,
]);
$topics_parent->save();
echo "Created menu link: Topics (parent)\n";

// Topics children.
$topics = \Drupal::entityTypeManager()
  ->getStorage('taxonomy_term')
  ->loadByProperties(['vid' => 'topics', 'parent' => 0]);

$topic_weight = 0;
foreach ($topics as $term) {
  $child = MenuLinkContent::create([
    'title' => $term->getName(),
    'link' => ['uri' => 'internal:/topics/' . $term->id()],
    'menu_name' => 'main',
    'parent' => 'menu_link_content:' . $topics_parent->uuid(),
    'weight' => $topic_weight++,
    'expanded' => FALSE,
  ]);
  $child->save();
  echo "  Created topic link: {$term->getName()}\n";
}

// Create remaining main links.
foreach ($main_links as $link_data) {
  $link = MenuLinkContent::create($link_data);
  $link->save();
  echo "Created menu link: {$link_data['title']}\n";
}

// Donate button (external link).
$donate = MenuLinkContent::create([
  'title' => 'Donate',
  'link' => ['uri' => 'https://danafarber.jimmyfund.org/give/dana-farber-donate'],
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
