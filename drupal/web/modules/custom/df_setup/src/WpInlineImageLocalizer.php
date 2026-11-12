<?php

declare(strict_types=1);

namespace Drupal\df_setup;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileRepositoryInterface;
use GuzzleHttp\ClientInterface;

/**
 * Rewrites inline wp.com / wp-content image URLs onto local public files.
 */
final class WpInlineImageLocalizer {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileSystemInterface $fileSystem,
    private readonly FileRepositoryInterface $fileRepository,
    private readonly ClientInterface $httpClient,
  ) {}

  /**
   * Rewrites article and in-brief body, summary, and excerpt HTML.
   *
   * @return int
   *   Number of entities saved.
   */
  public function rewriteContentFields(): int {
    $storage = $this->entityTypeManager->getStorage('node');
    $nids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', ['article', 'in_brief'], 'IN')
      ->execute();
    $saved = 0;
    foreach (array_chunk($nids, 25) as $chunk) {
      foreach ($storage->loadMultiple($chunk) as $node) {
        $changed = FALSE;
        foreach (['body', 'field_summary', 'field_excerpt'] as $field_name) {
          if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
            continue;
          }
          $item = $node->get($field_name);
          $original = (string) $item->value;
          $rewritten = $this->rewriteHtml($original);
          if ($rewritten !== $original) {
            $value = ['value' => $rewritten];
            if (isset($item->format)) {
              $value['format'] = $item->format;
            }
            $node->set($field_name, $value);
            $changed = TRUE;
          }
        }
        if ($changed) {
          $node->save();
          $saved++;
        }
      }
    }
    return $saved;
  }

  public function rewriteHtml(string $html): string {
    if ($html === '' || (!str_contains($html, 'wp-content/uploads') && !str_contains($html, 'wp.com'))) {
      return $html;
    }
    return (string) preg_replace_callback(
      '#https?://(?:[a-z0-9.-]*wp\.com/)?(?:danafarberimpact\.org/)?wp-content/uploads/([^"\'\s>]+)#i',
      function (array $matches): string {
        $relative = rawurldecode((string) strtok($matches[1], '?'));
        $relative = ltrim(str_replace(['../', '..\\'], '', $relative), '/');
        if ($relative === '') {
          return $matches[0];
        }
        $uri = $this->ensureLocal($relative);
        if ($uri === NULL) {
          return $matches[0];
        }
        return \Drupal::service('file_url_generator')->generateString($uri);
      },
      $html,
    );
  }

  /**
   * Copies a vendored or downloaded upload into public://wp-content/uploads.
   */
  public function ensureLocal(string $relative): ?string {
    $relative = ltrim($relative, '/');
    $uri = 'public://wp-content/uploads/' . $relative;
    if ($this->isRealFile($uri)) {
      return $uri;
    }
    $basename = basename($relative);
    $wp_images = 'public://wp-images/' . $basename;
    if ($this->isRealFile($wp_images)) {
      return $wp_images;
    }
    $directory = $this->fileSystem->dirname($uri);
    if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      return NULL;
    }
    $source = 'https://danafarberimpact.org/wp-content/uploads/' . implode('/', array_map('rawurlencode', explode('/', $relative)));
    try {
      $response = $this->httpClient->request('GET', $source, ['timeout' => 20, 'http_errors' => FALSE]);
    }
    catch (\Throwable) {
      return NULL;
    }
    if ($response->getStatusCode() !== 200) {
      return NULL;
    }
    $body = (string) $response->getBody();
    if ($body === '' || str_starts_with(ltrim($body), '<')) {
      return NULL;
    }
    $file = $this->fileRepository->writeData($body, $uri, FileExists::Replace);
    return $file->getFileUri();
  }

  private function isRealFile(string $uri): bool {
    $path = $this->fileSystem->realpath($uri);
    return is_string($path) && is_file($path) && filesize($path) > 32;
  }

}
