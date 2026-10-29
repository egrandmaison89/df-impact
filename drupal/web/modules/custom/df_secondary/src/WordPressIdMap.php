<?php

declare(strict_types=1);

namespace Drupal\df_secondary;

use Drupal\Core\Extension\ModuleExtensionList;

/**
 * Maps WordPress post/page IDs to Drupal path aliases.
 */
final class WordPressIdMap {

  /**
   * @var array<int, string>|null
   */
  private ?array $map = NULL;

  /**
   * @var array<string, int>|null
   */
  private ?array $reverse = NULL;

  public function __construct(
    private readonly ModuleExtensionList $moduleList,
  ) {}

  /**
   * Drupal path alias (leading slash, no trailing slash) for a WP post ID.
   */
  public function pathForPostId(int $wp_id): ?string {
    $map = $this->loadMap();
    return $map[$wp_id] ?? NULL;
  }

  /**
   * WordPress post ID for a Drupal alias path (leading slash, no trailing slash).
   */
  public function postIdForPath(string $drupal_alias): ?int {
    $normalized = '/' . trim($drupal_alias, '/');
    if ($normalized === '/') {
      return NULL;
    }
    $reverse = $this->loadReverse();
    return $reverse[$normalized] ?? NULL;
  }

  /**
   * @return array<int, string>
   */
  private function loadMap(): array {
    if ($this->map !== NULL) {
      return $this->map;
    }
    $this->map = [];
    $root = $this->moduleList->getPath('df_migrate');
    $files = [
      $root . '/data/articles.json',
      $root . '/data/in_brief.json',
      $root . '/data/issues.json',
    ];
    $pages_file = dirname($root, 2) . '/migration-data/pages_raw.json';
    if (is_readable($pages_file)) {
      $files[] = $pages_file;
    }
    foreach ($files as $path) {
      if (!is_readable($path)) {
        continue;
      }
      $rows = json_decode((string) file_get_contents($path), TRUE);
      if (!is_array($rows)) {
        continue;
      }
      foreach ($rows as $row) {
        if (!is_array($row)) {
          continue;
        }
        $id = (int) ($row['wp_id'] ?? $row['id'] ?? 0);
        if ($id <= 0) {
          continue;
        }
        $alias = $row['drupal_alias'] ?? NULL;
        if (!$alias && !empty($row['path_alias'])) {
          $alias = $row['path_alias'];
        }
        if (!$alias && !empty($row['link'])) {
          $alias = parse_url((string) $row['link'], PHP_URL_PATH);
        }
        if (!is_string($alias) || $alias === '') {
          continue;
        }
        $alias = '/' . trim($alias, '/');
        $this->map[$id] = $alias;
      }
    }
    return $this->map;
  }

  /**
   * @return array<string, int>
   */
  private function loadReverse(): array {
    if ($this->reverse !== NULL) {
      return $this->reverse;
    }
    $this->reverse = [];
    foreach ($this->loadMap() as $wp_id => $alias) {
      $this->reverse[$alias] = $wp_id;
    }
    return $this->reverse;
  }

}
