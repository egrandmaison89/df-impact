<?php

/**
 * @file
 * Syncs article/in-brief paths and homepage placement from df_migrate JSON + maps.
 *
 * Creates redirects from redirects.json and from dated WordPress URLs (/YYYY/MM/slug).
 *
 * Usage (from repo drupal/ with DDEV):
 *   ddev drush php:script web/modules/custom/df_migrate/scripts/df_sync_wordpress_paths.php
 */

use Drupal\node\Entity\Node;
use Drupal\pathauto\PathautoState;
use Drupal\redirect\Entity\Redirect;

$docroot = \Drupal::root();
$data_dir = $docroot . '/modules/custom/df_migrate/data';
$articles_file = $data_dir . '/articles.json';
$inbrief_file = $data_dir . '/in_brief.json';
$redirects_file = $data_dir . '/redirects.json';

if (!is_readable($articles_file)) {
  throw new \RuntimeException('Missing ' . $articles_file);
}

$articles = json_decode(file_get_contents($articles_file), TRUE);
$inbrief = is_readable($inbrief_file) ? json_decode(file_get_contents($inbrief_file), TRUE) : [];
$redirect_rows = is_readable($redirects_file) ? json_decode(file_get_contents($redirects_file), TRUE) : [];

$database = \Drupal::database();
$article_map = $database->select('migrate_map_df_articles', 'm')
  ->fields('m', ['sourceid1', 'destid1'])
  ->execute()
  ->fetchAllKeyed();

$brief_map = [];
if ($database->schema()->tableExists('migrate_map_df_in_brief')) {
  $brief_map = $database->select('migrate_map_df_in_brief', 'm')
    ->fields('m', ['sourceid1', 'destid1'])
    ->execute()
    ->fetchAllKeyed();
}

$placement_map = [
  'featured' => 'featured',
  'recent_highlights' => 'recent_highlights',
  'none' => 'none',
  'digital_exclusives' => 'none',
];

$de_tid = NULL;
if (\Drupal::hasService('df_setup.digital_exclusive_channels')) {
  /** @var \Drupal\df_setup\DigitalExclusiveChannels $de_channels */
  $de_channels = \Drupal::service('df_setup.digital_exclusive_channels');
  $de_tid = $de_channels->ensureTermId();
}
if (!$de_tid) {
  $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
  $de_tids = $term_storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('vid', 'channels')
    ->condition('name', 'Digital Exclusives')
    ->range(0, 1)
    ->execute();
  $de_tid = $de_tids ? (int) reset($de_tids) : NULL;
}

$pathauto_skip = class_exists(PathautoState::class) ? PathautoState::SKIP : 0;

echo "Updating article nodes from articles.json...\n";
$article_updates = 0;
foreach ($articles as $row) {
  $wp_id = $row['wp_id'] ?? 0;
  if (empty($article_map[$wp_id])) {
    continue;
  }
  $node = Node::load($article_map[$wp_id]);
  if (!$node instanceof Node || $node->bundle() !== 'article') {
    continue;
  }
  $placement = $row['homepage_placement'] ?? 'none';
  $placement_val = $placement_map[$placement] ?? 'none';
  $node->set('field_homepage_placement', $placement_val);

  if ($de_tid && $node->hasField('field_channels')) {
    $want_de = !empty($row['channels']) && is_array($row['channels'])
      && in_array('Digital Exclusives', $row['channels'], TRUE);
    $has_de = FALSE;
    foreach ($node->get('field_channels')->getValue() as $item) {
      if ((int) ($item['target_id'] ?? 0) === $de_tid) {
        $has_de = TRUE;
        break;
      }
    }
    if ($want_de && !$has_de) {
      $node->get('field_channels')->appendItem(['target_id' => $de_tid]);
    }
    if (!$want_de && $has_de) {
      $new_vals = [];
      foreach ($node->get('field_channels')->getValue() as $item) {
        if ((int) ($item['target_id'] ?? 0) !== $de_tid) {
          $new_vals[] = $item;
        }
      }
      $node->set('field_channels', $new_vals);
    }
  }

  $alias = $row['drupal_alias'] ?? '';
  if (is_string($alias) && $alias !== '' && str_starts_with($alias, '/')) {
    $node->set('path', ['alias' => $alias, 'pathauto' => $pathauto_skip]);
  }
  $node->save();
  $article_updates++;

  // Dated WordPress permalink: /YYYY/MM/post-name/
  if (!empty($row['wp_url']) && !empty($alias)) {
    $path = parse_url($row['wp_url'], PHP_URL_PATH);
    if (is_string($path)) {
      $path = trim($path, '/');
      if (preg_match('#^\d{4}/\d{2}/[^/]+$#', $path)) {
        df_sync_create_redirect($path, ltrim($alias, '/'));
      }
    }
  }
}
echo "Articles updated: $article_updates\n";

echo "Updating In Brief nodes...\n";
$brief_updates = 0;
foreach ($inbrief as $row) {
  $wp_id = $row['wp_id'] ?? 0;
  if (empty($brief_map[$wp_id])) {
    continue;
  }
  $node = Node::load($brief_map[$wp_id]);
  if (!$node instanceof Node || $node->bundle() !== 'in_brief') {
    continue;
  }
  $alias = $row['drupal_alias'] ?? '';
  if (is_string($alias) && $alias !== '' && str_starts_with($alias, '/')) {
    $node->set('path', ['alias' => $alias, 'pathauto' => $pathauto_skip]);
    $node->save();
    $brief_updates++;
  }
  if (!empty($row['wp_url']) && !empty($alias)) {
    $path = parse_url($row['wp_url'], PHP_URL_PATH);
    if (is_string($path)) {
      $path = trim($path, '/');
      if (preg_match('#^\d{4}/\d{2}/[^/]+$#', $path)) {
        df_sync_create_redirect($path, ltrim($alias, '/'));
      }
    }
  }
}
echo "In Brief updated: $brief_updates\n";

echo "Importing redirects.json...\n";
$redirect_count = 0;
foreach ($redirect_rows as $r) {
  $source = $r['source'] ?? '';
  $target = $r['redirect_to'] ?? '';
  if ($source === '' || $target === '') {
    continue;
  }
  $source = trim($source, '/');
  $target = ltrim($target, '/');
  if (df_sync_create_redirect($source, $target)) {
    $redirect_count++;
  }
}
echo "Redirects created or already present (this run attempted): $redirect_count\n";

// Legacy front path and WordPress homepage slug.
df_sync_create_redirect('node', 'home');
df_sync_create_redirect('homepage', 'home');

echo "Done.\n";

/**
 * Creates a redirect if no matching source exists.
 *
 * @return bool
 *   TRUE if a new redirect was saved.
 */
function df_sync_create_redirect(string $source_path, string $target_path): bool {
  $source_path = mb_strtolower(rtrim(ltrim($source_path, '/'), '/'));
  $target_path = ltrim($target_path, '/');
  if ($source_path === '' || $target_path === '') {
    return FALSE;
  }

  $storage = \Drupal::entityTypeManager()->getStorage('redirect');
  $language = \Drupal::languageManager()->getDefaultLanguage()->getId();
  $hash = Redirect::generateHash($source_path, [], $language);
  $existing = $storage->loadByProperties(['hash' => $hash]);
  if ($existing) {
    return FALSE;
  }

  $redirect = Redirect::create();
  $redirect->setSource($source_path);
  $redirect->setRedirect($target_path);
  $redirect->setStatusCode(301);
  $redirect->setLanguage($language);
  $redirect->save();
  return TRUE;
}
