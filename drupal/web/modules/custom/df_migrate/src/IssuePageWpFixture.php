<?php

declare(strict_types=1);

namespace Drupal\df_migrate;

/**
 * Tracked WordPress issue landing data (no live HTTP required on deploy).
 */
final class IssuePageWpFixture {

  private static ?array $cache = NULL;

  /**
   * @return array{issues: list<array<string, mixed>>}|null
   */
  public static function load(): ?array {
    if (self::$cache !== NULL) {
      return self::$cache;
    }
    $paths = [
      DRUPAL_ROOT . '/modules/custom/df_migrate/data/issue_page_wp_fixture.json',
      dirname(DRUPAL_ROOT, 2) . '/drupal/web/modules/custom/df_migrate/data/issue_page_wp_fixture.json',
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
      if (is_array($decoded) && isset($decoded['issues']) && is_array($decoded['issues'])) {
        self::$cache = $decoded;
        return self::$cache;
      }
    }
    self::$cache = NULL;
    return NULL;
  }

  /**
   * @return array<string, mixed>|null
   *   Fixture row keyed by normalized path alias (with leading/trailing slashes).
   */
  public static function rowForPathAlias(string $alias): ?array {
    $data = self::load();
    if ($data === NULL) {
      return NULL;
    }
    $norm = '/' . trim($alias, '/') . '/';
    foreach ($data['issues'] as $row) {
      if (!is_array($row)) {
        continue;
      }
      $row_alias = $row['path_alias'] ?? '';
      if (!is_string($row_alias)) {
        continue;
      }
      if ('/' . trim($row_alias, '/') . '/' === $norm) {
        return $row;
      }
    }
    return NULL;
  }

  /**
   * @return array<string, array<string, mixed>>
   *   Map of path alias => row.
   */
  public static function indexByAlias(): array {
    $data = self::load();
    $out = [];
    if ($data === NULL) {
      return $out;
    }
    foreach ($data['issues'] as $row) {
      if (!is_array($row) || empty($row['path_alias'])) {
        continue;
      }
      $out['/' . trim((string) $row['path_alias'], '/') . '/'] = $row;
    }
    return $out;
  }

}
