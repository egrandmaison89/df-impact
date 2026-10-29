<?php

/**
 * @file
 * Download missing images (WP / Photon), fix failed df_media_images rows, normalize URIs.
 *
 * Usage:
 *   drush php:script modules/custom/df_migrate/scripts/repair_drupal_images.php
 */

declare(strict_types=1);

use Drupal\Core\File\FileSystemInterface;
use Drupal\df_migrate\Plugin\migrate\process\DownloadImage;
use Drupal\file\FileInterface;
use Drupal\media\Entity\Media;

/**
 * Fetch binary from WP or i0.wp.com.
 */
require_once __DIR__ . '/restore_wp_images.inc';

function df_repair_fetch(string $url): ?string {
  $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
  if ($url === '') {
    return NULL;
  }
  $candidates = [$url];
  if (preg_match('#wp-content/uploads/(.+)$#i', $url, $m)) {
    $candidates[] = 'https://i0.wp.com/danafarberimpact.org/wp-content/uploads/' . $m[1] . '?ssl=1';
  }
  foreach ($candidates as $try) {
    $data = @file_get_contents($try, FALSE, stream_context_create([
      'http' => ['timeout' => 45, 'user_agent' => 'Drupal/11 DFMigrate/repair'],
    ]));
    if ($data !== FALSE && df_restore_valid_image($data)) {
      return $data;
    }
  }
  return NULL;
}

/**
 * Write bytes to a public URI, return URI or NULL.
 */
function df_repair_write_uri(string $uri, string $data): ?string {
  $fs = \Drupal::service('file_system');
  $dir = $fs->dirname($uri);
  $fs->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
  /** @var \Drupal\file\FileRepositoryInterface $repo */
  $repo = \Drupal::service('file.repository');
  $file = $repo->writeData($data, $uri, FileSystemInterface::EXISTS_REPLACE);
  return $file ? $uri : NULL;
}

/**
 * Ensure image media exists for a managed file URI.
 */
function df_repair_media_for_uri(string $uri, string $alt = ''): ?int {
  $files = \Drupal::entityTypeManager()->getStorage('file')->loadByProperties(['uri' => $uri]);
  $file = $files ? reset($files) : NULL;
  if (!$file instanceof FileInterface) {
    return NULL;
  }
  $fid = (int) $file->id();
  $existing = \Drupal::entityQuery('media')
    ->condition('bundle', 'image')
    ->condition('field_media_image.target_id', $fid)
    ->accessCheck(FALSE)
    ->range(0, 1)
    ->execute();
  if ($existing) {
    return (int) reset($existing);
  }
  if ($alt === '') {
    $alt = ucfirst(str_replace(['-', '_'], ' ', pathinfo($file->getFilename(), PATHINFO_FILENAME)));
  }
  $media = Media::create([
    'bundle' => 'image',
    'uid' => 1,
    'name' => $file->getFilename(),
    'field_media_image' => ['target_id' => $fid, 'alt' => $alt],
    'status' => 1,
  ]);
  $media->save();
  return (int) $media->id();
}

/**
 * Retry df_media_images rows with source_row_status = 3 (failed).
 */
function df_repair_failed_media_migration(): array {
  $db = \Drupal::database();
  $failed = $db->select('migrate_map_df_media_images', 'm')
    ->fields('m', ['sourceid1'])
    ->condition('source_row_status', 3)
    ->execute()
    ->fetchCol();
  $json_path = DRUPAL_ROOT . '/modules/custom/df_migrate/data/media.json';
  $rows = json_decode((string) file_get_contents($json_path), TRUE, 512, JSON_THROW_ON_ERROR);
  $by_id = [];
  foreach ($rows as $row) {
    if (isset($row['wp_id'])) {
      $by_id[(int) $row['wp_id']] = $row;
    }
  }

  $stats = ['failed_ids' => count($failed), 'fixed' => 0, 'still_failed' => 0];

  foreach ($failed as $wp_id) {
    $wp_id = (int) $wp_id;
    $source = $by_id[$wp_id] ?? NULL;
    if (!$source) {
      $stats['still_failed']++;
      continue;
    }
    $url = (string) ($source['source_url'] ?? '');
    $relative = '';
    if ($url !== '' && preg_match('#wp-content/uploads/(.+)$#i', $url, $m)) {
      $relative = $m[1];
    }
    $uri = $relative !== ''
      ? 'public://wp-content/uploads/' . $relative
      : DownloadImage::urlToUri($url);

    $data = $url !== '' ? df_repair_fetch($url) : NULL;
    if ($data === NULL && $relative !== '') {
      $wp_images = 'public://wp-images/' . basename($relative);
      $fs = \Drupal::service('file_system');
      $path = $fs->realpath($wp_images);
      if ($path && is_readable($path)) {
        $data = file_get_contents($path);
      }
    }
    if ($data === NULL) {
      $stats['still_failed']++;
      continue;
    }
    if (df_repair_write_uri($uri, $data) === NULL) {
      $stats['still_failed']++;
      continue;
    }
    $basename = basename($relative ?: parse_url($url, PHP_URL_PATH) ?: 'image.jpg');
    df_repair_write_uri('public://wp-images/' . rawurldecode($basename), $data);

    $fid = DownloadImage::getOrCreateFileFromUrl($url !== '' ? $url : $uri);
    if ($fid === NULL) {
      $stats['still_failed']++;
      continue;
    }
    df_repair_media_for_uri($uri, (string) ($source['alt_text'] ?? ''));
    $db->update('migrate_map_df_media_images')
      ->fields([
        'source_row_status' => 0,
        'rollback_action' => 0,
        'last_imported' => time(),
        'destid1' => (string) $fid,
      ])
      ->condition('sourceid1', (string) $wp_id)
      ->execute();
    $stats['fixed']++;
  }
  return $stats;
}

/**
 * Copy theme footer logos into public://wp-images when missing.
 */
function df_repair_footer_logos(): int {
  $theme = DRUPAL_ROOT . '/themes/custom/df_impact/images';
  $names = ['Dana-Farber-Logo.png', 'JFlogo_187.png'];
  $copied = 0;
  foreach ($names as $name) {
    $src = $theme . '/' . $name;
    if (!is_readable($src)) {
      continue;
    }
    $uri = 'public://wp-images/' . $name;
    $fs = \Drupal::service('file_system');
    $dest = $fs->realpath($uri);
    if ($dest && is_readable($dest) && filesize($dest) > 256) {
      continue;
    }
    $data = file_get_contents($src);
    if ($data && df_repair_write_uri($uri, $data)) {
      df_repair_media_for_uri($uri, $name);
      $copied++;
    }
  }
  return $copied;
}

/**
 * Fix http://default file URLs in node HTML fields.
 */
function df_repair_http_default_urls(): int {
  $base = rtrim(\Drupal::request()->getSchemeAndHttpHost(), '/');
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $updated = 0;
  foreach ($storage->loadMultiple($storage->getQuery()->accessCheck(FALSE)->execute()) as $node) {
    $changed = FALSE;
    foreach (['body', 'field_summary', 'field_excerpt'] as $field) {
      if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
        continue;
      }
      $value = (string) $node->get($field)->value;
      if (!str_contains($value, 'http://default/')) {
        continue;
      }
      $new = str_replace('http://default/', $base . '/', $value);
      if ($new !== $value) {
        $node->set($field, ['value' => $new, 'format' => $node->get($field)->format ?? 'full_html']);
        $changed = TRUE;
      }
    }
    if ($changed) {
      $node->save();
      $updated++;
    }
  }
  return $updated;
}

/**
 * Download missing files listed in latest parity image audit.
 */
function df_repair_from_audit_report(): array {
  $report = dirname(DRUPAL_ROOT, 2) . '/parity/reports/drupal-image-audit/latest-summary.json';
  if (!is_readable($report)) {
    return ['downloaded' => 0, 'skipped' => 0];
  }
  $data = json_decode((string) file_get_contents($report), TRUE, 512, JSON_THROW_ON_ERROR);
  $downloaded = 0;
  $skipped = 0;
  foreach ($data['broken'] ?? [] as $item) {
    $src = (string) ($item['src'] ?? '');
    if ($src === '' || str_starts_with($src, 'data:')) {
      $skipped++;
      continue;
    }
    $rel = NULL;
    if (preg_match('#/sites/default/files/(.+)$#', $src, $m)) {
      $rel = $m[1];
    }
    if ($rel === NULL) {
      if (preg_match('#danafarberimpact\.org/wp-content/uploads/(.+)$#i', $src, $m)) {
        $url = 'https://danafarberimpact.org/wp-content/uploads/' . explode('?', $m[1])[0];
        $rel = 'wp-content/uploads/' . explode('?', $m[1])[0];
      }
      else {
        $skipped++;
        continue;
      }
    }
    else {
      $url = 'https://danafarberimpact.org/wp-content/uploads/' . preg_replace('#^wp-images/#', '', $rel);
      if (str_starts_with($rel, 'wp-content/uploads/')) {
        $url = 'https://danafarberimpact.org/' . $rel;
      }
    }
    $uri = 'public://' . ltrim($rel, '/');
    $fs = \Drupal::service('file_system');
    $path = $fs->realpath($uri);
    if ($path && is_readable($path) && filesize($path) > 256) {
      $skipped++;
      continue;
    }
    $bin = df_repair_fetch($url);
    if ($bin === NULL) {
      $skipped++;
      continue;
    }
    if (df_repair_write_uri($uri, $bin)) {
      $downloaded++;
      if (str_starts_with($rel, 'wp-content/uploads/')) {
        df_repair_write_uri('public://wp-images/' . basename($rel), $bin);
      }
    }
  }
  return ['downloaded' => $downloaded, 'skipped' => $skipped];
}

echo "repair_drupal_images: start\n";
$logos = df_repair_footer_logos();
echo "footer logos copied: $logos\n";
$default = df_repair_http_default_urls();
echo "nodes fixed http://default: $default\n";
$audit = df_repair_from_audit_report();
echo 'audit-driven downloads: ' . json_encode($audit) . "\n";
$media = df_repair_failed_media_migration();
echo 'failed media migration: ' . json_encode($media) . "\n";
\Drupal::service('cache.render')->invalidateAll();
echo "repair_drupal_images: done\n";
