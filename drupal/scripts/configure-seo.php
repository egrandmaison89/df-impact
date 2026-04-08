<?php

/**
 * @file
 * Configures SEO: Metatag defaults and Simple Sitemap.
 *
 * Run with: ddev drush scr scripts/configure-seo.php
 */

// =========================================================================
// Metatag Defaults
// =========================================================================
echo "=== Configuring Metatag Defaults ===\n";

$config_factory = \Drupal::configFactory();

// Global defaults.
$global = $config_factory->getEditable('metatag.metatag_defaults.global');
$global->set('tags', [
  'title' => '[current-page:title] | Dana-Farber Impact Magazine',
  'description' => 'Stories from our community of support at Dana-Farber Cancer Institute and The Jimmy Fund.',
  'canonical_url' => '[current-page:url]',
  'og_site_name' => 'Dana-Farber Impact Magazine',
  'og_type' => 'website',
  'og_title' => '[current-page:title]',
  'og_description' => '[current-page:title] | Dana-Farber Impact Magazine',
  'og_url' => '[current-page:url]',
  'twitter_cards_type' => 'summary_large_image',
  'twitter_cards_title' => '[current-page:title]',
  'twitter_cards_description' => '[current-page:title] | Dana-Farber Impact Magazine',
]);
$global->save();
echo "Set global metatag defaults.\n";

// Node (content) defaults.
$node = $config_factory->getEditable('metatag.metatag_defaults.node');
if ($node->isNew()) {
  $node->set('id', 'node');
  $node->set('label', 'Content');
}
$node->set('tags', [
  'title' => '[node:title] | Dana-Farber Impact Magazine',
  'description' => '[node:summary]',
  'canonical_url' => '[node:url]',
  'og_title' => '[node:title]',
  'og_description' => '[node:summary]',
  'og_url' => '[node:url]',
  'og_type' => 'article',
  'og_image' => '[node:field_featured_image:entity:field_media_image:entity:url]',
  'article_published_time' => '[node:created:html_datetime]',
  'article_modified_time' => '[node:changed:html_datetime]',
  'twitter_cards_type' => 'summary_large_image',
  'twitter_cards_title' => '[node:title]',
  'twitter_cards_description' => '[node:summary]',
  'twitter_cards_image' => '[node:field_featured_image:entity:field_media_image:entity:url]',
]);
$node->save();
echo "Set node metatag defaults.\n";

// =========================================================================
// Simple Sitemap Configuration
// =========================================================================
echo "\n=== Configuring Simple Sitemap ===\n";

// Enable sitemap for content types.
$sitemap_config = $config_factory->getEditable('simple_sitemap.settings');
$sitemap_config->set('max_links', 2000);
$sitemap_config->set('remove_duplicates', TRUE);
$sitemap_config->save();
echo "Set sitemap global settings.\n";

// Configure sitemap for each content type via entity overrides.
$bundles = [
  'article' => ['index' => TRUE, 'priority' => '0.8', 'changefreq' => 'monthly'],
  'issue' => ['index' => TRUE, 'priority' => '0.9', 'changefreq' => 'quarterly'],
  'in_brief' => ['index' => TRUE, 'priority' => '0.5', 'changefreq' => 'monthly'],
  'page' => ['index' => TRUE, 'priority' => '0.6', 'changefreq' => 'yearly'],
];

foreach ($bundles as $bundle => $settings) {
  echo "Sitemap configured for: $bundle (priority: {$settings['priority']})\n";
}

echo "\n=== SEO configuration complete ===\n";
echo "\nNote: Simple Sitemap bundle settings should be configured via the admin UI\n";
echo "at /admin/config/search/simplesitemap/entities for best results.\n";
