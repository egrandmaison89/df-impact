<?php

declare(strict_types=1);

namespace Drupal\df_setup;

/**
 * Frozen WordPress archive listing order (from reference-pack captures).
 */
final class ArchivePageWpOrder {

  private static ?array $cache = NULL;

  /**
   * @return list<string>
   *   Normalized article paths in WP display order.
   */
  public static function pathsForArchive(string $publicPath): array {
    $data = self::load();
    if ($data === NULL) {
      return [];
    }
    $norm = '/' . trim($publicPath, '/') . '/';
    $norm = rtrim($norm, '/') ?: '/';
    $key = $norm === '//' ? '/' : rtrim($norm, '/');
    if ($key !== '/' && !str_starts_with($key, '/')) {
      $key = '/' . $key;
    }
    $key = rtrim($key, '/') ?: '/';
    $archives = $data['archives'] ?? [];
    return is_array($archives[$key] ?? NULL) ? $archives[$key] : [];
  }

  /**
   * @return array<string, mixed>|null
   */
  /**
   * @return array<string, mixed>|null
   */
  private static function load(): ?array {
    if (self::$cache !== NULL) {
      return self::$cache;
    }
    $paths = [
      DRUPAL_ROOT . '/modules/custom/df_migrate/data/archive_page_wp_order.json',
      dirname(DRUPAL_ROOT, 2) . '/drupal/web/modules/custom/df_migrate/data/archive_page_wp_order.json',
    ];
    foreach ($paths as $path) {
      if (!is_readable($path)) {
        continue;
      }
      $raw = file_get_contents($path);
      if ($raw === FALSE || $raw === '') {
        continue;
      }
      $decoded = json_decode($raw, TRUE);
      if (is_array($decoded)) {
        self::$cache = $decoded;
        return self::$cache;
      }
    }
    self::$cache = NULL;
    return NULL;
  }

}
