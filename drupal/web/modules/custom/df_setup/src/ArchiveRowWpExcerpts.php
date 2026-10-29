<?php

declare(strict_types=1);

namespace Drupal\df_setup;

/**
 * Listing excerpts captured from WordPress reference-pack archive HTML.
 */
final class ArchiveRowWpExcerpts {

  /** @var array<string, string>|null */
  private static ?array $map = NULL;

  public static function excerptForAlias(string $alias): ?string {
    $normalized = '/' . ltrim($alias, '/');
    $map = self::map();
    return $map[$normalized] ?? NULL;
  }

  /**
   * @return array<string, string>
   */
  private static function map(): array {
    if (self::$map !== NULL) {
      return self::$map;
    }
    self::$map = [];
    $file = DRUPAL_ROOT . '/modules/custom/df_migrate/data/archive_row_wp_excerpts.json';
    if (!is_readable($file)) {
      return self::$map;
    }
    try {
      $data = json_decode((string) file_get_contents($file), TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      return self::$map;
    }
    if (!is_array($data['excerpts'] ?? NULL)) {
      return self::$map;
    }
    foreach ($data['excerpts'] as $alias => $text) {
      if (!is_string($alias) || !is_string($text) || $text === '') {
        continue;
      }
      $key = '/' . ltrim($alias, '/');
      self::$map[$key] = $text;
    }
    return self::$map;
  }

}
