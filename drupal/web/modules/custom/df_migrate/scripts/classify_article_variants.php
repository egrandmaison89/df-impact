<?php

/**
 * @file
 * Classify article + in_brief nodes by layout variant; pick sample paths.
 *
 * Usage (from drupal/):
 *   drush php:script web/modules/custom/df_migrate/scripts/classify_article_variants.php
 */

declare(strict_types=1);

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * @return array<string, mixed>
 */
function df_migrate_classify_node(NodeInterface $node): array {
  $body = '';
  if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
    $body = (string) $node->get('body')->value;
  }
  $sections = '';
  if ($node->hasField('field_content_sections') && !$node->get('field_content_sections')->isEmpty()) {
    foreach ($node->get('field_content_sections') as $item) {
      $sections .= (string) ($item->value ?? '');
    }
  }
  $html = $body . $sections;
  $plain_len = strlen(strip_tags($html));

  $has_hero = FALSE;
  if ($node->hasField('field_featured_image') && !$node->get('field_featured_image')->isEmpty()) {
    $has_hero = TRUE;
  }
  elseif (preg_match('/wp-block-image|figure class="wp-block-image|<img[^>]+class="[^"]*wp-image/', $body)) {
    $has_hero = TRUE;
  }

  $flags = [
    'hero' => $has_hero,
    'no_hero' => !$has_hero,
    'video_embed' => (bool) preg_match('/wp-block-embed-youtube|wp-block-embed-vimeo|youtube\.com|youtu\.be|vimeo\.com/i', $html),
    'gallery' => (bool) preg_match('/wp-block-gallery|tiled-gallery|class="gallery/i', $html),
    'pull_quote' => (bool) preg_match('/wp-block-pullquote|class="[^"]*pullquote/i', $html),
    'sidebar' => (bool) preg_match('/wp-block-column[^>]*width|ultp-is-sticky|class="[^"]*sidebar/i', $html)
      || (bool) preg_match('/alignright size-(?:full|large)/', $body),
    'long_form' => $plain_len >= 6000,
    'custom_blocks' => (bool) preg_match('/wp-block-stackable|stk-|ultp-|wp-block-postx|postx-/i', $html)
      || ($node->hasField('field_content_sections') && !$node->get('field_content_sections')->isEmpty()),
  ];

  $year = NULL;
  if (preg_match('#^/(\d{4})/#', $node->toUrl()->toString(), $m)) {
    $year = (int) $m[1];
  }
  if ($year !== NULL) {
    if ($year >= 2022 && $year <= 2023) {
      $flags['year_2022_2023'] = TRUE;
    }
    elseif ($year >= 2024 && $year <= 2026) {
      $flags['year_2024_2026'] = TRUE;
    }
  }

  $variant_keys = [];
  foreach ($flags as $key => $on) {
    if (!$on || in_array($key, ['no_hero'], TRUE)) {
      continue;
    }
    if ($key === 'hero') {
      $variant_keys[] = 'hero';
    }
    else {
      $variant_keys[] = $key;
    }
  }
  if (!$has_hero) {
    $variant_keys[] = 'no_hero';
  }

  return [
    'nid' => (int) $node->id(),
    'bundle' => $node->bundle(),
    'path' => $node->toUrl()->toString(),
    'title' => $node->label(),
    'plain_len' => $plain_len,
    'flags' => $flags,
    'variants' => array_values(array_unique($variant_keys)),
  ];
}

$storage = \Drupal::entityTypeManager()->getStorage('node');
$nids = $storage->getQuery()
  ->condition('type', ['article', 'in_brief'], 'IN')
  ->condition('status', 1)
  ->accessCheck(FALSE)
  ->execute();

$by_variant = [];
$all = [];
foreach ($nids as $nid) {
  $node = $storage->load($nid);
  if (!$node instanceof NodeInterface) {
    continue;
  }
  $row = df_migrate_classify_node($node);
  $all[] = $row;
  foreach ($row['variants'] as $variant) {
    $by_variant[$variant][] = $row;
  }
}

$samples = [];
foreach ($by_variant as $variant => $rows) {
  usort($rows, static fn(array $a, array $b): int => strcmp($a['path'], $b['path']));
  $pick = [];
  foreach ($rows as $row) {
    if (count($pick) >= 2) {
      break;
    }
    $pick[] = $row['path'];
  }
  $samples[$variant] = [
    'count' => count($rows),
    'paths' => $pick,
  ];
}

$out = [
  'generated_at' => gmdate('c'),
  'totals' => [
    'nodes' => count($all),
    'variants' => count($by_variant),
  ],
  'samples' => $samples,
];

$dir = dirname(dirname(DRUPAL_ROOT)) . '/parity/reports/progress/article-variants-parity';
if (!is_dir($dir)) {
  mkdir($dir, 0775, TRUE);
}
$file = $dir . '/variant-classification.json';
file_put_contents($file, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
print "Wrote {$file}\n";
print json_encode($out['totals'], JSON_PRETTY_PRINT) . "\n";
