#!/usr/bin/env php
<?php

/**
 * @file
 * Import URL redirects from WordPress slugs to Drupal paths.
 *
 * WP URL: /slug/ -> Drupal: /stories/slug or /in-brief/slug
 * Also handles WP category/tag archive URLs -> Drupal topic pages
 */

use Drupal\redirect\Entity\Redirect;

$redirects_file = '/var/www/html/web/modules/custom/df_migrate/data/redirects.json';
if (!file_exists($redirects_file)) {
  echo "ERROR: redirects.json not found at $redirects_file\n";
  exit(1);
}

$redirects_data = json_decode(file_get_contents($redirects_file), TRUE);
echo "Loaded " . count($redirects_data) . " redirect rules\n";

$created = 0;
$skipped = 0;
$errors = 0;

// Check if redirect already exists
$storage = \Drupal::entityTypeManager()->getStorage('redirect');

foreach ($redirects_data as $item) {
  $source = $item['source'];
  $destination = $item['redirect_to'];

  // Skip if source and destination are the same
  if ($source === $destination) {
    $skipped++;
    continue;
  }

  // Skip empty
  if (empty($source) || empty($destination)) {
    $skipped++;
    continue;
  }

  // Clean up source path
  $source = ltrim($source, '/');
  $source = rtrim($source, '/');

  // Check for existing redirect
  $existing = $storage->loadByProperties([
    'redirect_source__path' => $source,
  ]);

  if (!empty($existing)) {
    $skipped++;
    continue;
  }

  try {
    // Create the redirect
    $redirect = Redirect::create([
      'redirect_source' => ['path' => $source],
      'redirect_redirect' => ['uri' => 'internal:' . $destination],
      'status_code' => 301,
      'language' => 'und',
    ]);
    $redirect->save();
    $created++;

    if ($created % 100 === 0) {
      echo "Created $created redirects...\n";
    }
  } catch (\Exception $e) {
    $errors++;
    if ($errors <= 5) {
      echo "ERROR on $source -> $destination: " . $e->getMessage() . "\n";
    }
  }
}

// Also add WP-style redirects for WordPress's default URL structure
// WP used /{slug}/ and sometimes /year/month/slug/ patterns
// The main ones we need: slug directly in root

// Add redirects for WP category archive URLs
$category_redirects = [
  'category/cancer-research' => '/topics/cancer-research',
  'category/grassroots-support' => '/topics/grassroots-support',
  'category/total-patient-care' => '/topics/total-patient-care',
  'category/access-and-equity' => '/topics/access-and-equity',
  'category/immunotherapy' => '/topics/immunotherapy',
  'category/drug-development' => '/topics/drug-development',
  'category/discovery-science' => '/topics/discovery-science',
  'category/prevention-and-early-detection' => '/topics/prevention-and-early-detection',
  'category/pediatrics' => '/topics/pediatrics',
  'category/planned-giving' => '/topics/planned-giving',
  'category/recognition' => '/topics/recognition',
  'category/digital-exclusives' => '/digital-exclusives',
];

foreach ($category_redirects as $source => $dest) {
  $existing = $storage->loadByProperties(['redirect_source__path' => $source]);
  if (empty($existing)) {
    try {
      $redirect = Redirect::create([
        'redirect_source' => ['path' => $source],
        'redirect_redirect' => ['uri' => 'internal:' . $dest],
        'status_code' => 301,
        'language' => 'und',
      ]);
      $redirect->save();
      $created++;
    } catch (\Exception $e) {
      // Silently skip
    }
  }
}

echo "\n=== REDIRECT IMPORT COMPLETE ===\n";
echo "Created:  $created\n";
echo "Skipped:  $skipped\n";
echo "Errors:   $errors\n";
