<?php

namespace Drupal\df_migrate;

use Drupal\df_migrate\Plugin\migrate\process\DownloadImage;
use Drupal\file\FileInterface;

/**
 * Rewrites WordPress inline image markup to local Drupal file URLs.
 */
final class InlineImageHtmlProcessor {

  /**
   * Localize remote WordPress images in HTML; download to public://wp-images.
   *
   * Uses absolute URLs for img src so images resolve regardless of path aliases,
   * &lt;base&gt; tags, or environment. Idempotent for already-fixed markup.
   */
  public static function localize(string $html): string {
    if ($html === '') {
      return $html;
    }

    $doc = new \DOMDocument();
    $wrapped = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="df-migrate-root">' . $html . '</div></body></html>';
    libxml_use_internal_errors(TRUE);
    $doc->loadHTML($wrapped);
    libxml_clear_errors();

    $xpath = new \DOMXPath($doc);
    $images = $xpath->query('//div[@id="df-migrate-root"]//img');
    if ($images === FALSE || $images->length === 0) {
      return $html;
    }

    $fileUrlGenerator = \Drupal::service('file_url_generator');
    $changed = FALSE;

    foreach ($images as $img) {
      if (!$img instanceof \DOMElement) {
        continue;
      }

      $dataSrc = html_entity_decode($img->getAttribute('data-src'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
      $src = html_entity_decode($img->getAttribute('src'), ENT_QUOTES | ENT_HTML5, 'UTF-8');

      $wpUrl = '';
      if ($dataSrc !== '' && self::isWordPressImageUrl($dataSrc)) {
        $wpUrl = $dataSrc;
      }
      elseif ($src !== '' && self::isWordPressImageUrl($src) && !str_starts_with($src, 'data:')) {
        $wpUrl = $src;
      }

      if ($wpUrl !== '') {
        $fid = DownloadImage::getOrCreateFileFromUrl($wpUrl);
        if ($fid === NULL) {
          continue;
        }
        /** @var \Drupal\file\FileInterface|null $file */
        $file = \Drupal::entityTypeManager()->getStorage('file')->load($fid);
        if (!$file instanceof FileInterface) {
          continue;
        }
        $img->setAttribute('src', $fileUrlGenerator->generateAbsoluteString($file->getFileUri()));
        $img->removeAttribute('data-src');
        $img->removeAttribute('data-srcset');
        $img->removeAttribute('data-sizes');
        $img->removeAttribute('data-recalc-dims');
        $class = $img->getAttribute('class');
        $class = trim(preg_replace('/\blazyload\b/', '', $class));
        if ($class === '') {
          $img->removeAttribute('class');
        }
        else {
          $img->setAttribute('class', $class);
        }
        $img->removeAttribute('srcset');
        $changed = TRUE;
        continue;
      }

      // Already pointing at our public files: normalize to absolute URL (and fix missing DB rows).
      $publicUri = self::publicUriFromImgAttributes($img);
      if ($publicUri === NULL || !str_starts_with($publicUri, 'public://wp-images/')) {
        continue;
      }

      $file = self::loadFileByUri($publicUri);
      if (!$file instanceof FileInterface) {
        continue;
      }

      $absolute = $fileUrlGenerator->generateAbsoluteString($file->getFileUri());
      if ($src !== $absolute) {
        $img->setAttribute('src', $absolute);
        $changed = TRUE;
      }
    }

    if (!$changed) {
      return $html;
    }

    $root = $doc->getElementById('df-migrate-root');
    if (!$root) {
      return $html;
    }

    $out = '';
    foreach ($root->childNodes as $child) {
      $out .= $doc->saveHTML($child);
    }

    return $out;
  }

  /**
   * Returns TRUE if HTML references wp-images files that are not on disk.
   */
  public static function bodyReferencesMissingFiles(string $html): bool {
    if (!str_contains($html, 'wp-images')) {
      return FALSE;
    }
    if (!preg_match_all('#(?:src|data-src)\\s*=\\s*["\']([^"\']+)#i', $html, $matches)) {
      return FALSE;
    }
    $fs = \Drupal::service('file_system');
    foreach ($matches[1] as $raw) {
      $uri = self::publicUriFromUrlString($raw);
      if ($uri === NULL || !str_starts_with($uri, 'public://wp-images/')) {
        continue;
      }
      $real = $fs->realpath($uri);
      if ($real === FALSE || !is_file($real)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Whether the URL points at WordPress media for this site (including Photon).
   */
  public static function isWordPressImageUrl(string $url): bool {
    return str_contains($url, 'danafarberimpact.org');
  }

  /**
   * Gets public:// URI for an img that already uses /sites/default/files/ paths.
   */
  private static function publicUriFromImgAttributes(\DOMElement $img): ?string {
    foreach (['src', 'data-src'] as $attr) {
      $val = html_entity_decode($img->getAttribute($attr), ENT_QUOTES | ENT_HTML5, 'UTF-8');
      if ($val !== '' && !str_starts_with($val, 'data:')) {
        $uri = self::publicUriFromUrlString($val);
        if ($uri !== NULL) {
          return $uri;
        }
      }
    }
    return NULL;
  }

  private static function publicUriFromUrlString(string $url): ?string {
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($url === '') {
      return NULL;
    }
    $path = parse_url($url, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
      if (preg_match('#^(https?://[^/]+)(/sites/default/files/wp-images/.+)$#i', $url, $m)) {
        $path = $m[2];
      }
      elseif (str_starts_with($url, '/sites/default/files/')) {
        $path = explode('?', $url, 2)[0];
      }
      else {
        return NULL;
      }
    }
    $path = explode('?', $path, 2)[0];
    if (!preg_match('#^/sites/default/files/(wp-images/.+)$#', $path, $m)) {
      return NULL;
    }
    return 'public://' . $m[1];
  }

  /**
   * Loads the managed file for a public:// URI if it exists.
   */
  private static function loadFileByUri(string $uri): ?FileInterface {
    $ids = \Drupal::entityQuery('file')
      ->condition('uri', $uri)
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();
    if (!$ids) {
      return NULL;
    }
    $file = \Drupal::entityTypeManager()->getStorage('file')->load(reset($ids));
    return $file instanceof FileInterface ? $file : NULL;
  }

}
