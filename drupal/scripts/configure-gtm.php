<?php
/**
 * Configure Google Tag Manager container.
 *
 * Replace GTM-XXXXXXX with the actual GTM container ID before running.
 */

use Drupal\google_tag\Entity\TagContainer;

$container_id = 'GTM-XXXXXXX'; // Replace with actual GTM ID

$existing = TagContainer::load('df_impact_gtm');
if ($existing) {
  echo "GTM container already configured: " . $existing->id() . "\n";
  exit;
}

$container = TagContainer::create([
  'id' => 'df_impact_gtm',
  'label' => 'Dana-Farber Impact GTM',
  'status' => TRUE,
  'tag_container_ids' => [$container_id],
  'conditions' => [],
]);
$container->save();
echo "GTM container created with ID: $container_id\n";
echo "Update the container_id and re-run when you have the actual GTM ID.\n";
