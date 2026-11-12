<?php

declare(strict_types=1);

namespace Drupal\df_setup;

/**
 * Maps WordPress upload URLs onto flat local image files.
 */
final class LocalWpImage {

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
   * Image extensions browsers and the static server will not serve as octet-stream.
   */
  private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

  /**
   * Real image in a flat directory.
   *
   * Prefers a file whose name matches, including a different case, and a file
   * that has an image extension. Extensionless originals are used only when no
   * sibling with an extension exists, and then a copy with an extension is
   * returned so the URL is not served as application/octet-stream.
   */
  /**
   * Fountain photo basenames that collide with the ski photo on case-insensitive disks.
   *
   * Keys are case-sensitive. The lowercase ski files stay DFI_suzanne.jpg.
   *
   * @return array<string, string>
   */
  public static function caseCollisionRenames(): array {
    return [
      'DFI_Suzanne.jpg' => 'DFI_Suzanne-Fountain-2024-11.jpg',
      'DFI_Suzanne-1024x734.jpg' => 'DFI_Suzanne-Fountain-2024-11.jpg',
    ];
  }

  /**
   * Unique basename for a known case collision, or NULL when the name can stay.
   */
  public static function caseCollisionRename(string $basename): ?string {
    return self::caseCollisionRenames()[basename($basename)] ?? NULL;
  }

  public static function findInDirectory(string $directory, string $basename): ?string {
    $basename = basename($basename);
    if ($basename === '' || !is_dir($directory)) {
      return NULL;
    }
    $renamed = self::caseCollisionRename($basename);
    if ($renamed !== NULL) {
      $target = $directory . '/' . $renamed;
      return self::isImageFile($target) ? $target : NULL;
    }
    $exact = $directory . '/' . $basename;
    if (self::hasImageExtension($basename) && self::isImageFile($exact)) {
      return $exact;
    }
    $requested_stem = self::stem($basename);
    $matches = [];
    foreach (self::imageIndex($directory) as $name) {
      if (self::stem($name) !== $requested_stem) {
        continue;
      }
      $matches[] = $name;
    }
    if ($matches === []) {
      return NULL;
    }
    usort($matches, static function (string $a, string $b) use ($basename): int {
      return self::matchRank($a, $basename) <=> self::matchRank($b, $basename);
    });
    return self::withImageExtension($directory . '/' . $matches[0]);
  }

  /**
   * Lowercase stem with a trailing WordPress size suffix removed.
   */
  private static function stem(string $filename): string {
    $stem = pathinfo($filename, PATHINFO_FILENAME);
    $stem = preg_replace('/-\d+x\d+$/', '', $stem) ?? $stem;
    return strtolower($stem);
  }

  /**
   * @return list<string>
   *   Basenames of real images in the directory.
   */
  private static function imageIndex(string $directory): array {
    static $cache = [];
    if (isset($cache[$directory])) {
      return $cache[$directory];
    }
    $names = [];
    foreach (scandir($directory) ?: [] as $name) {
      if ($name === '.' || $name === '..') {
        continue;
      }
      if (self::isImageFile($directory . '/' . $name)) {
        $names[] = $name;
      }
    }
    $cache[$directory] = $names;
    return $names;
  }

  /**
   * Sort key: extensioned files, then the requested name, then the unsuffixed original, then crops.
   *
   * @return array{int, int, int, int}
   */
  private static function matchRank(string $filename, string $requested): array {
    $extension_penalty = self::hasImageExtension($filename) ? 0 : 1;
    $exact = strtolower($filename) === strtolower($requested) ? 0 : 1;
    $file_stem = strtolower(pathinfo($filename, PATHINFO_FILENAME));
    $requested_stem = strtolower(pathinfo($requested, PATHINFO_FILENAME));
    $original = ($file_stem === $requested_stem || $file_stem === self::stem($requested)) && !self::isSizeVariant($filename) ? 0 : 1;
    return [$extension_penalty, $exact, $original, self::variantRank($filename)];
  }

  private static function isSizeVariant(string $filename): bool {
    return preg_match('/-\d+x\d+\.[a-z0-9]+$/i', $filename) === 1
      || preg_match('/-\d+x\d+$/i', pathinfo($filename, PATHINFO_FILENAME)) === 1;
  }

  private static function hasImageExtension(string $filename): bool {
    return in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), self::IMAGE_EXTENSIONS, TRUE);
  }

  /**
   * Copies an extensionless image to a sibling with a real extension.
   */
  private static function withImageExtension(string $path): string {
    if (self::hasImageExtension($path) || !self::isImageFile($path)) {
      return $path;
    }
    $extension = self::extensionFromMagic($path);
    if ($extension === NULL) {
      return $path;
    }
    $target = $path . '.' . $extension;
    if (!is_file($target) && is_writable(dirname($path))) {
      @copy($path, $target);
    }
    if (is_file($target) && self::isImageFile($target)) {
      return $target;
    }
    return $path;
  }

  private static function extensionFromMagic(string $path): ?string {
    $handle = fopen($path, 'rb');
    if ($handle === FALSE) {
      return NULL;
    }
    $magic = fread($handle, 16) ?: '';
    fclose($handle);
    if (str_starts_with($magic, "\xFF\xD8")) {
      return 'jpg';
    }
    if (str_starts_with($magic, "\x89PNG\r\n\x1a\n")) {
      return 'png';
    }
    if (str_starts_with($magic, 'GIF87a') || str_starts_with($magic, 'GIF89a')) {
      return 'gif';
    }
    if (str_starts_with($magic, 'RIFF') && str_contains($magic, 'WEBP')) {
      return 'webp';
    }
    return NULL;
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
