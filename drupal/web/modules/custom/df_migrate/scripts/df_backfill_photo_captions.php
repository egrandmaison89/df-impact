<?php

/**
 * @file
 * Backfills missing article photo captions from migration source data.
 *
 * Uses articles.json (WordPress export processed by process_wp_data.py) keyed
 * by migrate_map_df_articles, with fallbacks from body figcaptions and media alt.
 *
 * Usage (DDEV, from drupal/):
 *   ddev drush php:script web/modules/custom/df_migrate/scripts/df_backfill_photo_captions.php
 */

declare(strict_types=1);

use Drupal\df_setup\ArticleCreditLabels;
use Drupal\media\MediaInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/** @var \Drupal\df_setup\ArticleBodyCleaner $cleaner */
$cleaner = \Drupal::service('df_setup.article_body_cleaner');

$json_paths = [
  DRUPAL_ROOT . '/../migration-data/articles.json',
  DRUPAL_ROOT . '/modules/custom/df_migrate/data/articles.json',
];

$articles_by_wp_id = [];
$articles_by_slug = [];
foreach ($json_paths as $json_path) {
  if (!is_readable($json_path)) {
    continue;
  }
  $rows = json_decode((string) file_get_contents($json_path), TRUE);
  if (!is_array($rows)) {
    continue;
  }
  foreach ($rows as $row) {
    if (!is_array($row)) {
      continue;
    }
    $wp_id = (int) ($row['wp_id'] ?? 0);
    if ($wp_id > 0) {
      $articles_by_wp_id[$wp_id] = $row;
    }
    $slug = trim((string) ($row['slug'] ?? ''));
    if ($slug !== '') {
      $articles_by_slug[$slug] = $row;
    }
  }
  break;
}

if ($articles_by_wp_id === []) {
  echo "No articles.json source found; cannot backfill photo captions.\n";
  return;
}

$wp_to_nid = [];
$db = \Drupal::database();
if ($db->schema()->tableExists('migrate_map_df_articles')) {
  $result = $db->select('migrate_map_df_articles', 'm')
    ->fields('m', ['sourceid1', 'destid1'])
    ->execute();
  foreach ($result as $row) {
    $wp_to_nid[(int) $row->sourceid1] = (int) $row->destid1;
  }
}

$slug_to_nid = [];
$alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
$aliases = $alias_storage->loadByProperties(['langcode' => 'en']);
foreach ($aliases as $alias) {
  $path = $alias->getPath();
  if (!str_starts_with($path, '/node/')) {
    continue;
  }
  $nid = (int) substr($path, 6);
  $alias_path = trim($alias->getAlias(), '/');
  if (str_starts_with($alias_path, 'stories/')) {
    $slug_to_nid[substr($alias_path, 8)] = $nid;
  }
}

$nids = \Drupal::entityQuery('node')
  ->condition('type', 'article')
  ->accessCheck(FALSE)
  ->execute();

$updated = 0;
$caption_filled = 0;
$credit_fixed = 0;

foreach (Node::loadMultiple($nids) as $node) {
  if (!$node instanceof NodeInterface || $node->bundle() !== 'article') {
    continue;
  }
  if (!$node->hasField('field_photo_caption') || !$node->hasField('field_featured_image')) {
    continue;
  }
  if ($node->get('field_featured_image')->isEmpty()) {
    continue;
  }

  $changed = FALSE;
  $caption = $node->get('field_photo_caption')->isEmpty()
    ? ''
    : trim((string) $node->get('field_photo_caption')->value);

  if ($caption === '') {
    $source = function_exists('df_impact_article_export_row_for_node')
      ? df_impact_article_export_row_for_node($node)
      : _df_backfill_photo_captions_source_row($node, $wp_to_nid, $articles_by_wp_id, $articles_by_slug, $slug_to_nid);
    $caption = _df_backfill_photo_captions_resolve_caption($source, $node, $cleaner);
    if ($caption !== '') {
      $node->set('field_photo_caption', $caption);
      $changed = TRUE;
      $caption_filled++;
    }
  }

  if (_df_backfill_photo_captions_fix_credit($node, $caption)) {
    $changed = TRUE;
    $credit_fixed++;
  }

  if ($changed) {
    $node->save();
    $updated++;
  }
}

echo "Photo caption backfill complete.\n";
echo "Nodes updated: $updated\n";
echo "Captions filled: $caption_filled\n";
echo "Photo credits corrected: $credit_fixed\n";

/**
 * Finds the migration source row for a node.
 */
function _df_backfill_photo_captions_source_row(
  NodeInterface $node,
  array $wp_to_nid,
  array $articles_by_wp_id,
  array $articles_by_slug,
  array $slug_to_nid,
): ?array {
  $nid = (int) $node->id();
  $wp_id = array_search($nid, $wp_to_nid, TRUE);
  if ($wp_id !== FALSE && isset($articles_by_wp_id[(int) $wp_id])) {
    return $articles_by_wp_id[(int) $wp_id];
  }

  $slug = array_search($nid, $slug_to_nid, TRUE);
  if ($slug !== FALSE && isset($articles_by_slug[$slug])) {
    return $articles_by_slug[$slug];
  }

  return NULL;
}

/**
 * Resolves caption text from migration JSON and fallbacks.
 */
function _df_backfill_photo_captions_resolve_caption(
  ?array $source,
  NodeInterface $node,
  \Drupal\df_setup\ArticleBodyCleaner $cleaner,
): string {
  if (is_array($source)) {
    $caption = trim((string) ($source['photo_caption'] ?? ''));
    if ($caption !== '') {
      return $caption;
    }

    $body = (string) ($source['body'] ?? '');
    $featured_url = (string) ($source['featured_image_url'] ?? '');
    if ($body !== '') {
      $result = $cleaner->cleanArticleHtml($body, $featured_url !== '' ? $featured_url : NULL);
      if ($result['photo_caption'] !== '') {
        return $result['photo_caption'];
      }
    }
  }

  if (!$node->hasField('field_featured_image') || $node->get('field_featured_image')->isEmpty()) {
    return '';
  }

  $media = $node->get('field_featured_image')->entity;
  if (!$media instanceof MediaInterface || !$media->hasField('field_media_image')) {
    return '';
  }
  $alt = trim((string) ($media->get('field_media_image')->alt ?? ''));
  if ($alt === '' || _df_backfill_photo_captions_looks_like_photographer($alt)) {
    return '';
  }

  return $alt;
}

/**
 * Clears photo credit when it contains caption text instead of a photographer.
 */
function _df_backfill_photo_captions_fix_credit(NodeInterface $node, string $caption): bool {
  if (!$node->hasField('field_photo_credit') || $node->get('field_photo_credit')->isEmpty()) {
    return FALSE;
  }

  $credit = trim((string) $node->get('field_photo_credit')->value);
  if ($credit === '') {
    return FALSE;
  }

  $normalized_credit = ArticleCreditLabels::stripPhotoCredit($credit);
  if ($caption !== '' && strcasecmp($normalized_credit, $caption) === 0) {
    $node->set('field_photo_credit', '');
    return TRUE;
  }

  if (_df_backfill_photo_captions_looks_like_caption($normalized_credit)) {
    if ($caption === '') {
      $node->set('field_photo_caption', $normalized_credit);
    }
    $node->set('field_photo_credit', '');
    return TRUE;
  }

  return FALSE;
}

/**
 * Heuristic for caption-like subject lines stored in photo credit.
 */
function _df_backfill_photo_captions_looks_like_caption(string $value): bool {
  $value = trim($value);
  if ($value === '') {
    return FALSE;
  }
  return (bool) preg_match('/,\s*(?:MD|PhD|MPH|M\.D\.|Ph\.D\.|Dr\.|chief|division|director|professor)/i', $value);
}

/**
 * Avoid using generic photographer names from media alt text.
 */
function _df_backfill_photo_captions_looks_like_photographer(string $value): bool {
  $value = trim($value);
  if ($value === '') {
    return TRUE;
  }
  if (preg_match('/^(?:photograph(?:y)?|photos?)\s+by\b/i', $value)) {
    return TRUE;
  }
  return (bool) preg_match('/^[A-Z][a-z]+(?:\s+[A-Z][a-z]+){0,2}$/', $value);
}
