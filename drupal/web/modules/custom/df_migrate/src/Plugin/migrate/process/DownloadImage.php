<?php

namespace Drupal\df_migrate\Plugin\migrate\process;

use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use Drupal\media\Entity\Media;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Downloads an image from a URL, creates a file, and a media (image) entity.
 *
 * Article featured image fields are entity references to media, not files.
 *
 * Usage:
 * @code
 * field_featured_image:
 *   plugin: df_download_image
 *   source: featured_image_url
 *   media: true
 *
 * Image fields (core image type) must omit media or set media: false.
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
   * Static cache of fid -> media ID after media has been resolved or created.
   */
  protected static array $mediaByFid = [];

  /**
   * Downloads or reuses a managed file for a remote image URL.
   *
   * @param string $url
   *   Absolute URL (may contain HTML entities; will be normalized).
   *
   * @return int|null
   *   File entity ID, or NULL on failure.
   */
  public static function getOrCreateFileFromUrl(string $url): ?int {
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($url === '') {
      return NULL;
    }

    if (isset(static::$fileCache[$url])) {
      return static::$fileCache[$url];
    }

    $uri = static::urlToUri($url);
    $existing = \Drupal::entityQuery('file')
      ->condition('uri', $uri)
      ->accessCheck(FALSE)
      ->execute();

    if (!empty($existing)) {
      $fid = (int) reset($existing);
      static::$fileCache[$url] = $fid;
      return $fid;
    }

    try {
      $file_data = @file_get_contents($url, FALSE, stream_context_create([
        'http' => [
          'timeout' => 30,
          'user_agent' => 'Drupal/11 DFMigrate/1.0',
        ],
      ]));

      if ($file_data === FALSE) {
        \Drupal::logger('df_migrate')->warning('Failed to download image: @url', ['@url' => $url]);
        return NULL;
      }

      $destination_dir = 'public://wp-images';
      \Drupal::service('file_system')->prepareDirectory(
        $destination_dir,
        FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS
      );

      $filename = basename(parse_url($url, PHP_URL_PATH) ?? '');
      $filename = rawurldecode($filename);
      $destination = $destination_dir . '/' . $filename;

      /** @var \Drupal\file\FileInterface|false $file */
      $file = \Drupal::service('file.repository')->writeData(
        $file_data,
        $destination,
        FileSystemInterface::EXISTS_REPLACE
      );

      if (!$file) {
        \Drupal::logger('df_migrate')->warning('Failed to save image: @url', ['@url' => $url]);
        return NULL;
      }

      $fid = (int) $file->id();
      static::$fileCache[$url] = $fid;

      return $fid;
    }
    catch (\Exception $e) {
      \Drupal::logger('df_migrate')->error('Error downloading @url: @msg', [
        '@url' => $url,
        '@msg' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Convert a public URL to a Drupal URI (for checking existing files).
   */
  protected static function urlToUri(string $url): string {
    $filename = basename(parse_url($url, PHP_URL_PATH) ?? '');
    $filename = rawurldecode($filename);
    return 'public://wp-images/' . $filename;
  }

  /**
   * Returns an existing or new image media entity ID for a managed file.
   */
  public static function getOrCreateMediaFromFile(int $fid, Row $row): ?int {
    if (isset(static::$mediaByFid[$fid])) {
      return static::$mediaByFid[$fid];
    }

    /** @var \Drupal\file\FileInterface|null $file */
    $file = \Drupal::entityTypeManager()->getStorage('file')->load($fid);
    if (!$file instanceof FileInterface) {
      return NULL;
    }

    $existing = \Drupal::entityQuery('media')
      ->condition('bundle', 'image')
      ->condition('field_media_image.target_id', $fid)
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();

    if (!empty($existing)) {
      $mid = (int) reset($existing);
      static::$mediaByFid[$fid] = $mid;
      return $mid;
    }

    $alt = '';
    try {
      $title = $row->getSourceProperty('title');
      if (is_string($title) && $title !== '') {
        $alt = $title;
      }
    }
    catch (\Throwable $e) {
      // Source row may not define title; fall back below.
    }
    if ($alt === '') {
      $basename = pathinfo($file->getFilename(), PATHINFO_FILENAME);
      $alt = ucfirst(trim(str_replace(['-', '_'], ' ', $basename))) ?: 'Image';
    }

    try {
      $media = Media::create([
        'bundle' => 'image',
        'uid' => 1,
        'name' => $file->getFilename(),
        'field_media_image' => [
          'target_id' => $fid,
          'alt' => $alt,
        ],
        'status' => 1,
      ]);
      $media->save();
      $mid = (int) $media->id();
      static::$mediaByFid[$fid] = $mid;
      return $mid;
    }
    catch (\Exception $e) {
      \Drupal::logger('df_migrate')->error('Failed to create media for fid @fid: @msg', [
        '@fid' => $fid,
        '@msg' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (empty($value)) {
      return NULL;
    }

    $fid = static::getOrCreateFileFromUrl($value);
    if ($fid === NULL) {
      return NULL;
    }

    if (!empty($this->configuration['media'])) {
      $mid = static::getOrCreateMediaFromFile($fid, $row);
      return $mid !== NULL ? ['target_id' => $mid] : NULL;
    }

    return ['target_id' => $fid];
  }

}
