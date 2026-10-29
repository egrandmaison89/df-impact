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
  public function forPath(string $path): array {
    $this->load();
    $path = rtrim($path, '/') ?: '/';
    return $this->map[$path] ?? $this->map[rtrim($path, '/')] ?? [];
  }

  private function load(): void {
    if ($this->map !== NULL) {
      return;
    }
    $this->map = [];
    $root = DRUPAL_ROOT . '/modules/custom/df_metatag/data/wp_head_overrides.json';
    if (!is_readable($root)) {
      return;
    }
    $raw = json_decode((string) file_get_contents($root), TRUE);
    if (is_array($raw)) {
      $this->map = $raw;
    }
  }

}
