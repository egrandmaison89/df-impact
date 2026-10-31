<?php

declare(strict_types=1);

namespace Drupal\df_metatag;

use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Path-keyed Yoast head fields captured from WordPress reference HTML.
 */
final class WordPressHeadOverrides {

  /**
   * @var array<string, array<string, string>>|null
   */
  private ?array $map = NULL;

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * @return array{canonical?: string, og_image?: string, json_ld?: string}
   */
  private const PATH_ALIASES = [
    '/about' => '/about-impact',
    '/home' => '/homepage',
    '/search-legacy' => '/search',
  ];

  public function forPath(string $path): array {
    $this->load();
    $path = rtrim($path, '/') ?: '/';
    $alias = self::PATH_ALIASES[$path] ?? NULL;
    $base = $alias !== NULL ? ($this->map[$alias] ?? []) : [];
    $local = $this->map[$path] ?? [];
    if ($base === [] && $local === []) {
      return [];
    }
    return array_merge($base, $local);
  }

  private function load(): void {
    if ($this->map !== NULL) {
      return;
    }
    $this->map = [];
    foreach (['articles.json', 'in_brief.json'] as $file) {
      $path = DRUPAL_ROOT . '/modules/custom/df_migrate/data/' . $file;
      if (!is_readable($path)) {
        continue;
      }
      $rows = json_decode((string) file_get_contents($path), TRUE);
      if (!is_array($rows)) {
        continue;
      }
      foreach ($rows as $row) {
        if (($row['status'] ?? '') !== 'published' || empty($row['drupal_alias'])) {
          continue;
        }
        $key = rtrim((string) $row['drupal_alias'], '/') ?: '/';
        $title = (string) ($row['title'] ?? '');
        $suffix = ' - Impact Magazine';
        $full_title = str_ends_with($title, $suffix) ? $title : $title . $suffix;
        $canonical = (string) ($row['wp_url'] ?? '');
        if ($canonical !== '' && !str_ends_with($canonical, '/')) {
          $canonical .= '/';
        }
        $description = trim((string) ($row['meta_description'] ?? ''));
        if ($description === '') {
          $description = trim((string) ($row['excerpt'] ?? ''));
        }
        $this->map[$key] = [
          'title' => $full_title,
          'description' => $description,
          'og_title' => $full_title,
          'og_description' => $description,
          'canonical' => $canonical,
        ];
      }
    }

    $root = DRUPAL_ROOT . '/modules/custom/df_metatag/data/wp_head_overrides.json';
    if (is_readable($root)) {
      $raw = json_decode((string) file_get_contents($root), TRUE);
      if (is_array($raw)) {
        foreach ($raw as $path => $override) {
          $key = rtrim((string) $path, '/') ?: '/';
          $this->map[$key] = array_merge($this->map[$key] ?? [], $override);
        }
      }
    }
  }

}
