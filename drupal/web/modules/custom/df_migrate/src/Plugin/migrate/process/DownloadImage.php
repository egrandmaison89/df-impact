<?php

namespace Drupal\df_migrate\Plugin\migrate\process;

use Drupal\Core\File\FileSystemInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Downloads an image from a URL and creates a managed file entity.
 *
 * Usage:
 * @code
 * field_featured_image:
 *   plugin: df_download_image
 *   source: featured_image_url
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "df_download_image"
 * )
 */
class DownloadImage extends ProcessPluginBase {

  /**
   * Static cache of URL -> fid lookups to avoid duplicate downloads.
   */
  protected static array $fileCache = [];

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (empty($value)) {
      return NULL;
    }

    // Check cache first
    if (isset(static::$fileCache[$value])) {
      return ['target_id' => static::$fileCache[$value]];
    }

    // Check if a file with this URL already exists in the files table
    $uri = $this->urlToUri($value);
    $existing = \Drupal::entityQuery('file')
      ->condition('uri', $uri)
      ->accessCheck(FALSE)
      ->execute();

    if (!empty($existing)) {
      $fid = (int) reset($existing);
      static::$fileCache[$value] = $fid;
      return ['target_id' => $fid];
    }

    // Download the file
    try {
      $file_data = @file_get_contents($value, FALSE, stream_context_create([
        'http' => [
          'timeout' => 30,
          'user_agent' => 'Drupal/11 DFMigrate/1.0',
        ],
      ]));

      if ($file_data === FALSE) {
        \Drupal::logger('df_migrate')->warning('Failed to download image: @url', ['@url' => $value]);
        return NULL;
      }

      // Ensure destination directory exists
      $destination_dir = 'public://wp-images';
      \Drupal::service('file_system')->prepareDirectory(
        $destination_dir,
        FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS
      );

      // Get filename from URL
      $filename = basename(parse_url($value, PHP_URL_PATH));
      $filename = rawurldecode($filename);
      $destination = $destination_dir . '/' . $filename;

      // Save file
      /** @var \Drupal\file\FileInterface $file */
      $file = \Drupal::service('file.repository')->writeData(
        $file_data,
        $destination,
        FileSystemInterface::EXISTS_REPLACE
      );

      if (!$file) {
        \Drupal::logger('df_migrate')->warning('Failed to save image: @url', ['@url' => $value]);
        return NULL;
      }

      $fid = (int) $file->id();
      static::$fileCache[$value] = $fid;

      return ['target_id' => $fid];
    }
    catch (\Exception $e) {
      \Drupal::logger('df_migrate')->error('Error downloading @url: @msg', [
        '@url' => $value,
        '@msg' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Convert a public URL to a Drupal URI (for checking existing files).
   */
  protected function urlToUri(string $url): string {
    $filename = basename(parse_url($url, PHP_URL_PATH));
    $filename = rawurldecode($filename);
    return 'public://wp-images/' . $filename;
  }

}
