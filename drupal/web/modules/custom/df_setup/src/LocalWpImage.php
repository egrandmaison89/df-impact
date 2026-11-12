<?php

declare(strict_types=1);

namespace Drupal\df_setup;

/**
 * Maps WordPress upload URLs onto flat local image files.
 */
final class LocalWpImage {

  /**
   * Size suffixes WordPress generated for the card and banner crops.
   */
  private const VARIANT_SUFFIXES = ['-1024x734', '-1024x396'];

  public static function isImageFile(string $path): bool {
    if (!is_file($path) || !is_readable($path)) {
      return FALSE;
    }
    $handle = fopen($path, 'rb');
    if ($handle === FALSE) {
      return FALSE;
    }
    $magic = fread($handle, 16) ?: '';
    fclose($handle);
    if ($magic === '' || $magic[0] === '<') {
      return FALSE;
    }
    if (str_starts_with($magic, "\xFF\xD8")) {
      return TRUE;
    }
    if (str_starts_with($magic, "\x89PNG\r\n\x1a\n")) {
      return TRUE;
    }
    if (str_starts_with($magic, 'GIF87a') || str_starts_with($magic, 'GIF89a')) {
      return TRUE;
    }
    return str_starts_with($magic, 'RIFF') && str_contains($magic, 'WEBP');
  }

  /**
   * Basename of a WordPress upload or Photon URL, ignoring the dated folders.
   */
  public static function basenameFromUrl(string $url): ?string {
    $path = parse_url($url, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
      return NULL;
    }
    $path = rawurldecode($path);
    if (preg_match('#/wp-content/uploads/(.+)$#i', $path, $matches) !== 1) {
      return NULL;
    }
    $name = basename($matches[1]);
    if ($name === '' || $name === '.' || $name === '..') {
      return NULL;
    }
    return $name;
  }

  /**
   * Real image in a flat directory: exact name, then known crops, then stem.
   */
  public static function findInDirectory(string $directory, string $basename): ?string {
    $basename = basename($basename);
    if ($basename === '' || !is_dir($directory)) {
      return NULL;
    }
    $exact = $directory . '/' . $basename;
    if (self::isImageFile($exact)) {
      return $exact;
    }
    $extension = pathinfo($basename, PATHINFO_EXTENSION);
    $stem = pathinfo($basename, PATHINFO_FILENAME);
    $stem = preg_replace('/-\d+x\d+$/', '', $stem) ?? $stem;
    $extensions = array_values(array_unique(array_filter([
      $extension !== '' ? $extension : NULL,
      'jpg',
      'jpeg',
      'png',
      'webp',
      'gif',
    ])));
    foreach (self::VARIANT_SUFFIXES as $suffix) {
      foreach ($extensions as $ext) {
        $candidate = $directory . '/' . $stem . $suffix . '.' . $ext;
        if (self::isImageFile($candidate)) {
          return $candidate;
        }
      }
    }
    $matches = glob($directory . '/' . $stem . '*') ?: [];
    $real = [];
    foreach ($matches as $path) {
      if (self::isImageFile($path)) {
        $real[] = $path;
      }
    }
    if ($real === []) {
      return NULL;
    }
    usort($real, static function (string $a, string $b): int {
      return self::variantRank($a) <=> self::variantRank($b);
    });
    return $real[0];
  }

  private static function variantRank(string $path): int {
    $name = pathinfo($path, PATHINFO_FILENAME);
    if (str_contains($name, '-1024x734')) {
      return 0;
    }
    if (str_contains($name, '-1024x396')) {
      return 1;
    }
    return 2;
  }

}
