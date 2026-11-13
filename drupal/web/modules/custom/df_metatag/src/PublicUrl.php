<?php

declare(strict_types=1);

namespace Drupal\df_metatag;

/**
 * Absolute public URLs. WordPress hosts are never left in the output.
 */
final class PublicUrl {

  public static function isWordpressHost(string $host): bool {
    $host = strtolower($host);
    if ($host === '') {
      return FALSE;
    }
    return $host === 'wp.com'
      || str_ends_with($host, '.wp.com')
      || $host === 'danafarberimpact.org'
      || str_ends_with($host, '.danafarberimpact.org');
  }

  public static function isWordpressUrl(string $url): bool {
    $host = parse_url($url, PHP_URL_HOST);
    return is_string($host) && self::isWordpressHost($host);
  }

  /**
   * Move a WordPress-hosted URL onto the public origin. Other hosts stay.
   */
  public static function rewrite(string $url, string $origin): string {
    $origin = rtrim($origin, '/');
    if ($origin === '' || !self::isWordpressUrl($url)) {
      return $url;
    }
    $parts = parse_url($url);
    if (!is_array($parts)) {
      return $url;
    }
    $path = $parts['path'] ?? '';
    if (is_string($path) && preg_match('#^/(?:i\d+\.wp\.com/)?(?:www\.)?danafarberimpact\.org(/.*)$#i', $path, $matches) === 1) {
      $path = $matches[1];
    }
    $rebuilt = $origin . $path;
    if (!empty($parts['query'])) {
      $rebuilt .= '?' . $parts['query'];
    }
    return $rebuilt;
  }

}
