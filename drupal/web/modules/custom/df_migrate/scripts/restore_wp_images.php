<?php

/**
 * @file
 * Restore corrupt public://wp-images from git or live WP CDN.
 *
 * drush php:script modules/custom/df_migrate/scripts/restore_wp_images.php
 */

declare(strict_types=1);

use Drupal\Core\File\FileSystemInterface;

require_once __DIR__ . '/restore_wp_images.inc';

$repo_root = dirname(DRUPAL_ROOT, 2);
$wp_dir = DRUPAL_ROOT . '/sites/default/files/wp-images';

$stats = ['scanned' => 0, 'ok' => 0, 'restored_git' => 0, 'restored_remote' => 0, 'failed' => 0];

$files = glob($wp_dir . '/*') ?: [];
foreach ($files as $path) {
  if (!is_file($path)) {
    continue;
  }
  $stats['scanned']++;
  if (df_restore_path_is_image($path)) {
    $stats['ok']++;
    continue;
  }
  $basename = basename($path);
  $rel = 'drupal/web/sites/default/files/wp-images/' . $basename;
  $git = proc_open(
    ['git', '-C', $repo_root, 'show', 'origin/cursor/templates-inbrief-archives-185b:' . $rel],
    [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
    $pipes,
  );
  if (is_resource($git)) {
    fclose($pipes[0]);
    $blob = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($git);
    if ($blob !== FALSE && df_restore_valid_image($blob)) {
      file_put_contents($path, $blob);
      $stats['restored_git']++;
      continue;
    }
  }
  $data = df_restore_fetch($basename);
  if ($data !== NULL) {
    file_put_contents($path, $data);
    $uri = 'public://wp-images/' . $basename;
    \Drupal::service('file.repository')->writeData($data, $uri, FileSystemInterface::EXISTS_REPLACE);
    $stats['restored_remote']++;
    continue;
  }
  $stats['failed']++;
  fwrite(STDERR, "Could not restore: $basename\n");
}

echo json_encode($stats, JSON_PRETTY_PRINT) . "\n";
