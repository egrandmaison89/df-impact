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
    if ($html === '') {
      return $html;
    }
    $needs = str_contains($html, 'wp-content/uploads')
      || str_contains($html, 'wp.com')
      || str_contains($html, 'danafarberimpact.org')
      || str_contains($html, 'attachment_id=');
    if (!$needs) {
      return $html;
    }
    $html = (string) preg_replace_callback(
      '#https?://(?:[a-z0-9.-]*wp\.com/)?(?:danafarberimpact\.org/)?wp-content/uploads/([^"\'\s>]+)#i',
      function (array $matches): string {
        $relative = rawurldecode((string) strtok($matches[1], '?'));
        $relative = ltrim(str_replace(['../', '..\\'], '', $relative), '/');
        if ($relative === '') {
          return $this->placeholderWebPath();
        }
        $uri = $this->ensureLocal($relative);
        if ($uri === NULL) {
          return $this->placeholderWebPath();
        }
        return \Drupal::service('file_url_generator')->generateString($uri);
      },
      $html,
    );
    $html = (string) preg_replace_callback(
      '#https?://(?:www\.)?danafarberimpact\.org/(\d{4}/\d{2}/[^/"\'\s>]+)/([^/"\'\s>]+)/?#i',
      function (array $matches): string {
        $parent = '/' . $matches[1];
        $file = $this->ensureLocal($matches[2]);
        if ($file !== NULL && preg_match('/\.(?:jpe?g|png|gif|webp)$/i', $matches[2]) === 1) {
          return \Drupal::service('file_url_generator')->generateString($file);
        }
        return $parent;
      },
      $html,
    );
    return (string) preg_replace_callback(
      '#https?://(?:www\.)?danafarberimpact\.org/(?:\?|&)(?:[^"\'\s>]*&)?attachment_id=(\d+)#i',
      function (array $matches): string {
        $uri = $this->localFileForAttachmentId((int) $matches[1]);
        if ($uri === NULL) {
          return $this->placeholderWebPath();
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
    $basename = LocalWpImage::caseCollisionRename(basename($relative)) ?? basename($relative);
    $flat = $this->realFlatImage($basename);
    if ($flat !== NULL) {
      return $flat;
    }
    $uri = 'public://wp-content/uploads/' . $relative;
    if ($this->isRealFile($uri)) {
      return $uri;
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
    if (!str_starts_with($body, "\xFF\xD8") && !str_starts_with($body, "\x89PNG") && !str_starts_with($body, 'GIF8') && !str_starts_with($body, 'RIFF')) {
      return NULL;
    }
    $file = $this->fileRepository->writeData($body, 'public://wp-images/' . $basename, FileExists::Replace);
    $written = $file->getFileUri();
    return $this->isRealFile($written) ? $written : NULL;
  }

  private function realFlatImage(string $basename): ?string {
    $directory = $this->fileSystem->realpath('public://wp-images');
    if (!is_string($directory)) {
      return NULL;
    }
    $found = LocalWpImage::findInDirectory($directory, $basename);
    if ($found === NULL) {
      return NULL;
    }
    return 'public://wp-images/' . basename($found);
  }

  private function localFileForAttachmentId(int $wp_id): ?string {
    static $index = NULL;
    if ($index === NULL) {
      $index = [];
      $path = DRUPAL_ROOT . '/modules/custom/df_setup/data/media.json';
      if (is_readable($path)) {
        $items = json_decode((string) file_get_contents($path), TRUE);
        if (is_array($items)) {
          foreach ($items as $item) {
            if (!is_array($item) || empty($item['wp_id']) || empty($item['filename'])) {
              continue;
            }
            $index[(int) $item['wp_id']] = (string) $item['filename'];
          }
        }
      }
    }
    if (!isset($index[$wp_id])) {
      return NULL;
    }
    return $this->ensureLocal($index[$wp_id]);
  }

  private function placeholderWebPath(): string {
    $theme = \Drupal::service('extension.list.theme')->getPath('df_impact');
    return '/' . $theme . '/assets/image-placeholder.png';
  }

  private function isRealFile(string $uri): bool {
    $path = $this->fileSystem->realpath($uri);
    return is_string($path) && LocalWpImage::isImageFile($path);
  }

}
