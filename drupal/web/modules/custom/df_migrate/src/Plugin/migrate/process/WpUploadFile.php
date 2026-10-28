<?php

declare(strict_types=1);

namespace Drupal\df_migrate\Plugin\migrate\process;

use Drupal\Core\File\FileSystemInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Downloads a WordPress upload into public://wp-content/uploads/{relative path}.
 *
 * @MigrateProcessPlugin(
 *   id = "df_wp_upload_file"
 * )
 */
final class WpUploadFile extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $url = is_string($value) ? trim($value) : '';
    if ($url === '') {
      $url = (string) $row->getSourceProperty('source_url');
    }
    $relative = (string) ($row->getSourceProperty('wp_upload_path') ?? '');
    $relative = str_replace('\\', '/', ltrim($relative, '/'));
    if ($url === '' && $relative === '') {
      return NULL;
    }

    $uri = $relative !== ''
      ? 'public://wp-content/uploads/' . $relative
      : DownloadImage::urlToUri($url);

    $existing = \Drupal::entityQuery('file')
      ->condition('uri', $uri)
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();
    if ($existing) {
      return $uri;
    }

    $local_root = dirname(\Drupal::root(), 2) . '/migration-data/private/wp-uploads';
    if ($relative !== '' && is_readable($local_root . '/' . $relative)) {
      $data = file_get_contents($local_root . '/' . $relative);
      if ($data !== FALSE) {
        return $this->writeUri($uri, $data);
      }
    }

    if ($url !== '') {
      $data = $this->fetchUrl($url);
      if ($data !== NULL) {
        return $this->writeUri($uri, $data);
      }
      $fid = DownloadImage::getOrCreateFileFromUrl($url);
      if ($fid !== NULL && $relative !== '') {
        /** @var \Drupal\file\FileInterface|null $file */
        $file = \Drupal::entityTypeManager()->getStorage('file')->load($fid);
        if ($file) {
          $data = file_get_contents($file->getFileUri());
          if ($data !== FALSE) {
            return $this->writeUri($uri, $data);
          }
        }
      }
      if ($fid !== NULL) {
        $file = \Drupal::entityTypeManager()->getStorage('file')->load($fid);
        return $file ? $file->getFileUri() : NULL;
      }
    }

    return NULL;
  }

  private function fetchUrl(string $url): ?string {
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($url === '') {
      return NULL;
    }
    $data = @file_get_contents($url, FALSE, stream_context_create([
      'http' => [
        'timeout' => 45,
        'user_agent' => 'Drupal/11 DFMigrate/1.0',
      ],
    ]));
    if ($data === FALSE || strlen($data) < 256) {
      return NULL;
    }
    if (stripos($data, '<html') !== FALSE || stripos($data, 'sgcaptcha') !== FALSE) {
      return NULL;
    }
    return $data;
  }

  private function writeUri(string $uri, string $data): ?string {
    $fs = \Drupal::service('file_system');
    $dir = $fs->dirname($uri);
    $fs->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    /** @var \Drupal\file\FileRepositoryInterface $repo */
    $repo = \Drupal::service('file.repository');
    $file = $repo->writeData($data, $uri, FileSystemInterface::EXISTS_REPLACE);
    return $file ? $uri : NULL;
  }

}
