<?php

/**
 * @file
 * Backfills Issue nodes' field_cover_image from WordPress browse-issues page.
 *
 * Parses https://danafarberimpact.org/browse-issues/ for figure.issue-cover
 * blocks, downloads each cover, creates image media, attaches to Drupal issues
 * matched by season + year (from the WP issue URL slug).
 *
 * Usage (from Drupal docroot, e.g. via ddev):
 *   drush scr modules/custom/df_migrate/scripts/backfill_issue_covers_from_wordpress.php
 *   drush scr modules/custom/df_migrate/scripts/backfill_issue_covers_from_wordpress.php -- --dry-run
 *   drush scr modules/custom/df_migrate/scripts/backfill_issue_covers_from_wordpress.php -- --skip-existing
 */

use Drupal\df_migrate\Plugin\migrate\process\DownloadImage;
use Drupal\file\FileInterface;
use Drupal\media\Entity\Media;
use Drupal\node\NodeInterface;

$argv = $_SERVER['argv'] ?? [];
$dry_run = in_array('--dry-run', $argv, TRUE);
$skip_existing = in_array('--skip-existing', $argv, TRUE);

const WP_BROWSE_ISSUES_URL = 'https://danafarberimpact.org/browse-issues/';

$html = @file_get_contents(WP_BROWSE_ISSUES_URL, FALSE, stream_context_create([
  'http' => [
    'timeout' => 60,
    'user_agent' => 'Drupal/11 DFMigrate/issue-covers',
  ],
]));

if ($html === FALSE || $html === '') {
  fwrite(STDERR, "Failed to fetch " . WP_BROWSE_ISSUES_URL . "\n");
  exit(1);
}

$html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

$dom = new DOMDocument();
$internalErrors = libxml_use_internal_errors(TRUE);
$loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
libxml_use_internal_errors($internalErrors);
if (!$loaded) {
  fwrite(STDERR, "Failed to parse HTML.\n");
  exit(1);
}

$xpath = new DOMXPath($dom);
$figures = $xpath->query("//figure[contains(concat(' ', normalize-space(@class), ' '), ' issue-cover ')]");

$pairs = [];
/** @var DOMElement $figure */
foreach ($figures as $figure) {
  $anchors = $figure->getElementsByTagName('a');
  $issue_href = '';
  for ($i = 0; $i < $anchors->length; $i++) {
    $href = $anchors->item($i)->getAttribute('href');
    if ($href !== '' && str_contains($href, '-issue')) {
      $issue_href = $href;
      break;
    }
  }
  if ($issue_href === '') {
    continue;
  }

  $path = (string) (parse_url($issue_href, PHP_URL_PATH) ?? '');
  if (!preg_match('#/([a-z0-9-]+)-issue/?$#i', $path, $m)) {
    fwrite(STDERR, "Could not parse issue slug from: $issue_href\n");
    continue;
  }
  $wp_slug = strtolower($m[1]);

  $cover_url = '';
  $alt = '';
  $candidates = [];
  $imgs = $figure->getElementsByTagName('img');
  for ($j = 0; $j < $imgs->length; $j++) {
    /** @var DOMElement $img_el */
    $img_el = $imgs->item($j);
    foreach ([$img_el->getAttribute('data-src'), $img_el->getAttribute('src')] as $raw) {
      $candidate = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
      if ($candidate === '' || str_starts_with($candidate, 'data:')) {
        continue;
      }
      if (!str_contains($candidate, '/wp-content/uploads/')) {
        continue;
      }
      $candidates[] = df_issue_cover_canonical_url($candidate);
    }
    if ($alt === '') {
      $alt = trim($img_el->getAttribute('alt'));
    }
  }
  $candidates = array_values(array_unique(array_filter($candidates)));
  $cover_url = df_issue_cover_pick_best_upload_url($candidates);

  if ($cover_url === '') {
    fwrite(STDERR, "No image URL for issue slug: $wp_slug ($issue_href)\n");
    continue;
  }

  $pairs[$wp_slug] = [
    'cover_url' => $cover_url,
    'alt' => $alt !== '' ? $alt : 'Impact magazine cover',
    'href' => $issue_href,
  ];
}

if ($pairs === []) {
  fwrite(STDERR, "No issue/cover pairs found on WordPress page.\n");
  exit(1);
}

echo 'Found ' . count($pairs) . " issue covers on WordPress.\n";

$node_storage = \Drupal::entityTypeManager()->getStorage('node');
$updated = 0;
$skipped = 0;
$missing_node = 0;

foreach ($pairs as $wp_slug => $info) {
  $mapped = df_issue_cover_wp_slug_to_season_year($wp_slug);
  if ($mapped === NULL) {
    fwrite(STDERR, "Unrecognized WP slug (expected {season}-{year}): $wp_slug\n");
    continue;
  }
  [$season, $year] = $mapped;

  $nids = \Drupal::entityQuery('node')
    ->condition('type', 'issue')
    ->condition('status', 1)
    ->condition('field_year.value', (int) $year)
    ->condition('field_season.value', $season)
    ->accessCheck(FALSE)
    ->range(0, 2)
    ->execute();

  if (count($nids) !== 1) {
    fwrite(STDERR, 'Drupal issue not found or ambiguous for ' . $season . " $year (slug $wp_slug); nids=" . json_encode(array_values($nids)) . "\n");
    if ($nids === []) {
      $missing_node++;
    }
    continue;
  }

  $nid = (int) reset($nids);
  /** @var \Drupal\node\NodeInterface|null $node */
  $node = $node_storage->load($nid);
  if (!$node instanceof NodeInterface) {
    $missing_node++;
    continue;
  }

  if ($skip_existing && $node->hasField('field_cover_image') && !$node->get('field_cover_image')->isEmpty()) {
    echo 'Skip existing cover: ' . $node->label() . " (nid $nid)\n";
    $skipped++;
    continue;
  }

  echo ($dry_run ? '[dry-run] ' : '') . 'Set cover for ' . $node->label() . " (nid $nid) <= {$info['cover_url']}\n";

  if ($dry_run) {
    $updated++;
    continue;
  }

  $fid = DownloadImage::getOrCreateFileFromUrl($info['cover_url']);
  if ($fid === NULL) {
    fwrite(STDERR, "Download failed for nid $nid: {$info['cover_url']}\n");
    continue;
  }

  $mid = df_issue_cover_get_or_create_media($fid, $info['alt']);
  if ($mid === NULL) {
    fwrite(STDERR, "Could not create media for nid $nid, fid $fid\n");
    continue;
  }

  $node->set('field_cover_image', ['target_id' => $mid]);
  $node->save();
  $updated++;
}

echo "\nDone. Updated: $updated; skipped (--skip-existing): $skipped; missing/ambiguous node: $missing_node\n";

if (!$dry_run && $updated > 0) {
  drupal_flush_all_caches();
  echo "Caches rebuilt.\n";
}

/**
 * Picks the best cover URL (prefer full-size over -791x1024 style derivatives).
 *
 * @param list<string> $urls
 *   Canonical upload URLs.
 */
function df_issue_cover_pick_best_upload_url(array $urls): string {
  $urls = array_values(array_unique(array_filter($urls)));
  if ($urls === []) {
    return '';
  }
  $preferred = array_values(array_filter(
    $urls,
    static function (string $u): bool {
      $path = (string) (parse_url($u, PHP_URL_PATH) ?? '');
      return $path !== '' && !preg_match('#-\d+x\d+\.(webp|jpe?g|png)$#i', $path);
    },
  ));
  return $preferred[0] ?? $urls[0];
}

/**
 * Normalizes Jetpack/i0.wp.com URLs to danafarberimpact.org file URL (no query).
 */
function df_issue_cover_canonical_url(string $url): string {
  $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
  if (preg_match('#https?://i\d*\.wp\.com/danafarberimpact\.org(/.+)#i', $url, $m)) {
    $path = preg_replace('/\?.*$/', '', $m[1]);
    return 'https://danafarberimpact.org' . $path;
  }
  $parts = parse_url($url);
  if (!empty($parts['scheme']) && !empty($parts['host']) && !empty($parts['path'])) {
    return $parts['scheme'] . '://' . $parts['host'] . $parts['path'];
  }
  return $url;
}

/**
 * Maps WP path slug (e.g. spring-2026, late-fall-2022) to Drupal field values.
 */
function df_issue_cover_wp_slug_to_season_year(string $slug): ?array {
  if (!preg_match('/^(.+)-(\d{4})$/', $slug, $m)) {
    return NULL;
  }
  $season_part = $m[1];
  $year = $m[2];
  $season = str_replace('-', ' ', $season_part);
  // Drupal stores lowercase season keys.
  $season = strtolower($season);
  return [$season, $year];
}

/**
 * Creates or finds image media for a file; field_cover_image references media.
 */
function df_issue_cover_get_or_create_media(int $fid, string $alt): ?int {
  $existing = \Drupal::entityQuery('media')
    ->condition('bundle', 'image')
    ->condition('field_media_image.target_id', $fid)
    ->accessCheck(FALSE)
    ->range(0, 1)
    ->execute();

  if (!empty($existing)) {
    return (int) reset($existing);
  }

  /** @var \Drupal\file\FileInterface|null $file */
  $file = \Drupal::entityTypeManager()->getStorage('file')->load($fid);
  if (!$file instanceof FileInterface) {
    return NULL;
  }

  $alt = mb_substr($alt, 0, 512);
  $media = Media::create([
    'bundle' => 'image',
    'uid' => 1,
    'name' => $file->getFilename(),
    'field_media_image' => [
      'target_id' => $fid,
      'alt' => $alt !== '' ? $alt : 'Impact magazine cover',
    ],
    'status' => 1,
  ]);
  $media->save();
  return (int) $media->id();
}
