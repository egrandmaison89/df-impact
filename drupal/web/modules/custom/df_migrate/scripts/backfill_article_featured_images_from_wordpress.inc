<?php

/**
 * @file
 * Backfills article field_featured_image from WordPress export (articles.json).
 *
 * Included only after Drupal is fully bootstrapped. Run via Drush:
 *   drush df-migrate:backfill-article-featured
 */

declare(strict_types=1);

use Drupal\df_migrate\Plugin\migrate\process\DownloadImage;
use Drupal\file\FileInterface;
use Drupal\media\Entity\Media;
use Drupal\node\NodeInterface;

/**
 * Executes the backfill (requires full Drupal bootstrap).
 */
function df_migrate_backfill_article_featured_images_run(bool $dry_run, bool $live_fallback, ?int $only_wp_id): void {
  $articles_path = dirname(__DIR__) . '/data/articles.json';
  if (!is_readable($articles_path)) {
    fwrite(STDERR, "Missing $articles_path\n");
    return;
  }

  $rows = json_decode((string) file_get_contents($articles_path), TRUE);
  if (!is_array($rows)) {
    fwrite(STDERR, "Invalid JSON in articles.json\n");
    return;
  }

  $database = \Drupal::database();
  $wp_to_nid = [];
  if ($database->schema()->tableExists('migrate_map_df_articles')) {
    $wp_to_nid = $database->select('migrate_map_df_articles', 'm')
      ->fields('m', ['sourceid1', 'destid1'])
      ->execute()
      ->fetchAllKeyed();
    $wp_to_nid = array_map('intval', $wp_to_nid);
  }

  $node_storage = \Drupal::entityTypeManager()->getStorage('node');

  $stats = [
    'rows' => 0,
    'skipped_has_image' => 0,
    'skipped_no_nid' => 0,
    'skipped_no_url' => 0,
    'updated' => 0,
    'failed' => 0,
  ];

  foreach ($rows as $row) {
    if (!is_array($row)) {
      continue;
    }
    $stats['rows']++;
    $wp_id = (int) ($row['wp_id'] ?? 0);
    if ($only_wp_id && $wp_id !== $only_wp_id) {
      continue;
    }

    $nid = df_article_featured_resolve_nid($row, $wp_to_nid);
    if ($nid === NULL) {
      fwrite(STDERR, 'No Drupal article for wp_id ' . $wp_id . ' (' . ($row['slug'] ?? '') . ")\n");
      $stats['skipped_no_nid']++;
      continue;
    }

    /** @var \Drupal\node\NodeInterface|null $node */
    $node = $node_storage->load($nid);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'article') {
      $stats['skipped_no_nid']++;
      continue;
    }

    if (!$node->hasField('field_featured_image') || !$node->get('field_featured_image')->isEmpty()) {
      $stats['skipped_has_image']++;
      continue;
    }

    $title = (string) ($row['title'] ?? $node->label());
    $try_urls = df_article_featured_build_try_urls($row);

    if ($try_urls === []) {
      $stats['skipped_no_url']++;
      fwrite(STDERR, 'No image URL candidates for nid ' . $nid . ' ' . $title . "\n");
      continue;
    }

    $mid = NULL;
    $used = '';
    $source = '';

    if ($dry_run) {
      $first = $try_urls[0];
      echo '[dry-run] nid ' . $nid . ' wp_id ' . $wp_id . ' <= ' . $first['url'] . ' (' . $first['source'] . ")\n";
      $stats['updated']++;
      continue;
    }

    foreach ($try_urls as $item) {
      $url = df_article_featured_canonical_url(df_article_featured_absolutize($item['url']));
      if ($url === '') {
        continue;
      }
      $fid = DownloadImage::getOrCreateFileFromUrl($url);
      if ($fid === NULL) {
        continue;
      }
      $mid = df_article_featured_get_or_create_media($fid, $title);
      if ($mid !== NULL) {
        $used = $url;
        $source = $item['source'];
        break;
      }
    }

    if ($mid === NULL && $live_fallback && !empty($row['wp_url']) && is_string($row['wp_url'])) {
      $page_url = df_article_featured_absolutize($row['wp_url']);
      if ($page_url !== '') {
        $html = df_article_featured_http_get($page_url);
        sleep(1);
        if ($html !== '') {
          $og = df_article_featured_og_image($html);
          if ($og !== '') {
            $url = df_article_featured_canonical_url(df_article_featured_absolutize($og));
            if ($url !== '') {
              $fid = DownloadImage::getOrCreateFileFromUrl($url);
              if ($fid !== NULL) {
                $mid = df_article_featured_get_or_create_media($fid, $title);
                if ($mid !== NULL) {
                  $used = $url;
                  $source = 'live:og:image';
                }
              }
            }
          }
        }
      }
    }

    if ($mid === NULL) {
      fwrite(STDERR, 'Download/media failed for nid ' . $nid . ' ' . $title . "\n");
      $stats['failed']++;
      continue;
    }

    $node->set('field_featured_image', ['target_id' => $mid]);
    try {
      $node->save();
      $stats['updated']++;
      echo 'Set featured image nid ' . $nid . ' wp_id ' . $wp_id . ' (' . $source . ") <= $used\n";
    }
    catch (\Throwable $e) {
      fwrite(STDERR, 'Save failed nid ' . $nid . ': ' . $e->getMessage() . "\n");
      $stats['failed']++;
    }
  }

  echo "\nDone. rows={$stats['rows']} updated={$stats['updated']} has_image={$stats['skipped_has_image']} no_nid={$stats['skipped_no_nid']} no_url={$stats['skipped_no_url']} failed={$stats['failed']}\n";

  if (!$dry_run && $stats['updated'] > 0) {
    drupal_flush_all_caches();
    echo "Caches rebuilt.\n";
  }
}

/**
 * @param array<string, mixed> $row
 * @param array<int, int> $wp_to_nid
 */
function df_article_featured_resolve_nid(array $row, array $wp_to_nid): ?int {
  $wp_id = (int) ($row['wp_id'] ?? 0);
  if ($wp_id && isset($wp_to_nid[$wp_id])) {
    return (int) $wp_to_nid[$wp_id];
  }
  $alias = $row['drupal_alias'] ?? '';
  if (!is_string($alias) || $alias === '') {
    return NULL;
  }
  $alias = '/' . ltrim($alias, '/');
  $path_alias = \Drupal::service('path_alias.manager');
  foreach ([NULL, 'en'] as $langcode) {
    $internal = $langcode === NULL
      ? $path_alias->getPathByAlias($alias)
      : $path_alias->getPathByAlias($alias, $langcode);
    if (preg_match('#^/node/(\d+)$#', $internal, $m)) {
      return (int) $m[1];
    }
  }
  return NULL;
}

/**
 * @param array<string, mixed> $row
 *
 * @return list<array{source: string, url: string}>
 */
function df_article_featured_build_try_urls(array $row): array {
  $out = [];
  $seen = [];

  $push = function (string $source, string $url) use (&$out, &$seen): void {
    $url = trim($url);
    if ($url === '') {
      return;
    }
    $key = strtolower(df_article_featured_canonical_url(df_article_featured_absolutize($url)));
    if (isset($seen[$key])) {
      return;
    }
    $seen[$key] = TRUE;
    $out[] = ['source' => $source, 'url' => $url];
  };

  $primary = (string) ($row['featured_image_url'] ?? '');
  if ($primary !== '') {
    $push('articles.json:featured_image_url', $primary);
  }

  $body = isset($row['body']) && is_string($row['body']) ? $row['body'] : '';
  if ($body !== '') {
    $from_body = df_article_featured_first_upload_url($body);
    if ($from_body !== '') {
      $push('articles.json:body', $from_body);
    }
  }

  return $out;
}

function df_article_featured_absolutize(string $url): string {
  $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
  if ($url === '') {
    return '';
  }
  if (str_starts_with($url, '//')) {
    return 'https:' . $url;
  }
  if (str_starts_with($url, '/')) {
    return 'https://danafarberimpact.org' . $url;
  }
  return $url;
}

function df_article_featured_canonical_url(string $url): string {
  $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
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

function df_article_featured_first_upload_url(string $html): string {
  if (preg_match(
    '#https?://(?:i\d*\.wp\.com/)?danafarberimpact\.org/wp-content/uploads/[^\s"\'<>]+#i',
    $html,
    $m
  )) {
    return $m[0];
  }
  return '';
}

function df_article_featured_og_image(string $html): string {
  if (preg_match(
    '#<meta\s+property=["\']og:image["\']\s+content=["\']([^"\']+)#i',
    $html,
    $m
  )) {
    return trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
  }
  return '';
}

function df_article_featured_http_get(string $url): string {
  $ctx = stream_context_create([
    'http' => [
      'timeout' => 45,
      'user_agent' => 'Drupal/11 DFMigrate/article-featured-backfill',
    ],
    'ssl' => [
      'verify_peer' => TRUE,
      'verify_peer_name' => TRUE,
    ],
  ]);
  $data = @file_get_contents($url, FALSE, $ctx);
  return is_string($data) ? $data : '';
}

function df_article_featured_get_or_create_media(int $fid, string $alt): ?int {
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
  try {
    $media = Media::create([
      'bundle' => 'image',
      'uid' => 1,
      'name' => $file->getFilename(),
      'field_media_image' => [
        'target_id' => $fid,
        'alt' => $alt !== '' ? $alt : 'Article image',
      ],
      'status' => 1,
    ]);
    $media->save();
    return (int) $media->id();
  }
  catch (\Throwable $e) {
    \Drupal::logger('df_migrate')->error('Featured backfill media failed fid @fid: @msg', [
      '@fid' => $fid,
      '@msg' => $e->getMessage(),
    ]);
    return NULL;
  }
}
