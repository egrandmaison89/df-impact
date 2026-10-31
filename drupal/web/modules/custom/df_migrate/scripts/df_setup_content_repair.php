<?php

/**
 * @file
 * Idempotent repair: internal links, inline images, broken featured media.
 *
 * Used by df_setup post_update (drush updb). Safe to re-run.
 *
 * Link repair uses ArticleBodyCleaner::rewriteInternalLinksInHtml() only so
 * converge / late-fall post_updates are not undone in any execution order.
 */

declare(strict_types=1);

use Drupal\df_migrate\InlineImageHtmlProcessor;
use Drupal\df_setup\ArticleBodyCleaner;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Runs link rewrite, image localization, and featured-image repair.
 *
 * @return array<string, int|string>
 */
function df_setup_content_repair_run(bool $repair_featured = TRUE): array {
  /** @var \Drupal\df_setup\ArticleBodyCleaner $cleaner */
  $cleaner = \Drupal::service('df_setup.article_body_cleaner');

  require_once __DIR__ . '/backfill_article_featured_images_from_wordpress.inc';

  $fieldMap = [
    ['bundle' => 'article', 'field' => 'body', 'format' => 'full_html'],
    ['bundle' => 'in_brief', 'field' => 'field_summary', 'format' => 'full_html'],
  ];

  $links_updated = 0;
  $fields_scanned = 0;

  foreach ($fieldMap as $map) {
    $nids = \Drupal::entityQuery('node')
      ->condition('type', $map['bundle'])
      ->accessCheck(FALSE)
      ->execute();
    foreach (Node::loadMultiple($nids) as $node) {
      if (!$node->hasField($map['field']) || $node->get($map['field'])->isEmpty()) {
        continue;
      }
      $fields_scanned++;
      $original = (string) $node->get($map['field'])->value;
      $format = (string) ($node->get($map['field'])->format ?? $map['format']);
      $normalized = $cleaner->rewriteInternalLinksInHtml($original);
      if (trim($normalized) !== trim($original)) {
        $node->set($map['field'], ['value' => $normalized, 'format' => $format]);
        $node->save();
        $links_updated++;
      }
    }
  }

  $inline_updated = 0;
  $inline_scanned = 0;
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $nids = $storage->getQuery()
    ->condition('type', ['article', 'in_brief'], 'IN')
    ->accessCheck(FALSE)
    ->execute();
  foreach (Node::loadMultiple($nids) as $node) {
    if ($node->bundle() === 'article' && $node->hasField('body') && !$node->get('body')->isEmpty()) {
      $inline_scanned++;
      $field = $node->get('body');
      $original = (string) $field->value;
      $localized = InlineImageHtmlProcessor::localize($original);
      if ($localized !== $original) {
        $node->set('body', ['value' => $localized, 'format' => $field->format ?? 'full_html']);
        $node->save();
        $inline_updated++;
      }
    }
    elseif ($node->bundle() === 'in_brief' && $node->hasField('field_summary') && !$node->get('field_summary')->isEmpty()) {
      $inline_scanned++;
      $field = $node->get('field_summary');
      $original = (string) $field->value;
      $localized = InlineImageHtmlProcessor::localize($original);
      if ($localized !== $original) {
        $node->set('field_summary', ['value' => $localized, 'format' => $field->format ?? 'full_html']);
        $node->save();
        $inline_updated++;
      }
    }
  }

  $featured = ['scanned' => 0, 'updated' => 0, 'cleared' => 0, 'failed' => 0];
  if ($repair_featured) {
    $featured = df_migrate_repair_invalid_article_featured_images();
  }

  drupal_flush_all_caches();

  return [
    'fields_scanned' => $fields_scanned,
    'link_fields_updated' => $links_updated,
    'inline_nodes_scanned' => $inline_scanned,
    'inline_nodes_updated' => $inline_updated,
    'featured_scanned' => $featured['scanned'],
    'featured_updated' => $featured['updated'],
    'featured_cleared' => $featured['cleared'],
    'featured_failed' => $featured['failed'],
    'lead_body_resync' => 0,
  ];
}

if (PHP_SAPI === 'cli' && !empty($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
  echo json_encode(df_setup_content_repair_run(), JSON_PRETTY_PRINT) . "\n";
}
