<?php

/**
 * @file
 * Backfills oEmbed iframes across article, in-brief, issue, and landing bodies.
 *
 * Usage (DDEV, from drupal/):
 *   ddev drush php:script web/modules/custom/df_migrate/scripts/df_backfill_all_embeds.php
 */

use Drupal\node\Entity\Node;
use Drupal\df_setup\ArticleBodyCleaner;

/** @var \Drupal\df_setup\ArticleBodyCleaner $cleaner */
$cleaner = \Drupal::service('df_setup.article_body_cleaner');

/**
 * @return list<array{bundle: string, field: string, format: string}>
 */
function df_embed_field_map(): array {
  return [
    ['bundle' => 'article', 'field' => 'body', 'format' => 'full_html'],
    ['bundle' => 'in_brief', 'field' => 'field_summary', 'format' => 'full_html'],
    ['bundle' => 'page', 'field' => 'body', 'format' => 'full_html'],
    ['bundle' => 'issue', 'field' => 'field_description', 'format' => 'full_html'],
    ['bundle' => 'issue', 'field' => 'field_leadership_message', 'format' => 'full_html'],
    ['bundle' => 'issue', 'field' => 'field_issue_promo_2', 'format' => 'full_html'],
  ];
}

/**
 * @param array<string, int> $acc
 * @param array<string, int> $row
 */
function df_embed_add_counts(array &$acc, array $row): void {
  foreach ($row as $k => $v) {
    $acc[$k] = ($acc[$k] ?? 0) + (int) $v;
  }
}

$before = [
  'nodes_scanned' => 0,
  'fields_with_markers' => 0,
  'markers' => [
    'vimeo' => 0,
    'youtube' => 0,
    'twitter' => 0,
    'instagram' => 0,
    'plain_wrapper' => 0,
    'total_markers' => 0,
  ],
  'by_bundle' => [],
];

$after = $before;
$after['nodes_updated'] = 0;
$after['fields_updated'] = 0;

foreach (df_embed_field_map() as $map) {
  $bundle = $map['bundle'];
  $field = $map['field'];
  $nids = \Drupal::entityQuery('node')
    ->condition('type', $bundle)
    ->accessCheck(FALSE)
    ->execute();
  if (!$nids) {
    continue;
  }

  foreach (Node::loadMultiple($nids) as $node) {
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
      continue;
    }
    $before['nodes_scanned']++;
    $original = (string) ($node->get($field)->value ?? '');
    $format = (string) ($node->get($field)->format ?? $map['format']);
    $markers = ArticleBodyCleaner::countOembedConversionMarkers($original);
    if (($markers['plain_wrapper'] ?? 0) === 0 && ($markers['vimeo'] ?? 0) === 0 && ($markers['youtube'] ?? 0) === 0) {
      continue;
    }
    $before['fields_with_markers']++;
    df_embed_add_counts($before['markers'], $markers);
    if (!isset($before['by_bundle'][$bundle])) {
      $before['by_bundle'][$bundle] = ['fields' => 0, 'plain_wrapper' => 0];
    }
    $before['by_bundle'][$bundle]['fields']++;
    $before['by_bundle'][$bundle]['plain_wrapper'] += (int) ($markers['plain_wrapper'] ?? 0);

    $normalized = $cleaner->normalizeOembedHtml($original);
    if (trim($normalized) === trim($original)) {
      continue;
    }

    $node->set($field, ['value' => $normalized, 'format' => $format]);
    $node->save();
    $after['nodes_updated']++;
    $after['fields_updated']++;

    $afterMarkers = ArticleBodyCleaner::countOembedConversionMarkers($normalized);
    df_embed_add_counts($after['markers'], $afterMarkers);
    if (!isset($after['by_bundle'][$bundle])) {
      $after['by_bundle'][$bundle] = ['fields_updated' => 0];
    }
    $after['by_bundle'][$bundle]['fields_updated'] = ($after['by_bundle'][$bundle]['fields_updated'] ?? 0) + 1;
  }
}

$report = [
  'generated_at' => date('c'),
  'before' => $before,
  'after' => $after,
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
