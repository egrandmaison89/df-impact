<?php

declare(strict_types=1);

namespace Drupal\df_migrate;

/**
 * Resolve Drupal path aliases from WordPress export rows.
 */
final class WordpressPathHelper {

  /**
   * Canonical alias path (leading slash, no trailing slash except root).
   */
  public static function aliasFromRow(array $row): string {
    $wp_url = $row['wp_url'] ?? '';
    if (is_string($wp_url) && $wp_url !== '') {
      $path = parse_url($wp_url, PHP_URL_PATH);
      if (is_string($path) && $path !== '' && $path !== '/') {
        $path = rtrim($path, '/');
        return str_starts_with($path, '/') ? $path : '/' . $path;
      }
    }
    $alias = $row['drupal_alias'] ?? '';
    if (is_string($alias) && $alias !== '' && str_starts_with($alias, '/')) {
      return rtrim($alias, '/') ?: '/';
    }
    return '';
  }

}
