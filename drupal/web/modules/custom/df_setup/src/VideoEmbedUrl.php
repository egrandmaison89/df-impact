<?php

declare(strict_types=1);

namespace Drupal\df_setup;

/**
 * Turns a pasted YouTube or Vimeo URL into an embeddable iframe src.
 */
final class VideoEmbedUrl {

  public static function normalizeInput(string $value): string {
    $value = trim($value);
    if ($value === '') {
      return '';
    }
    if (!preg_match('#^[a-z][a-z0-9+.-]*:#i', $value)) {
      $value = 'https://' . ltrim($value, '/');
    }
    return $value;
  }

  public static function iframeSrc(string $url): ?string {
    $url = self::normalizeInput($url);
    if ($url === '') {
      return NULL;
    }
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) {
      return NULL;
    }
    $host = strtolower((string) $parts['host']);
    $path = (string) ($parts['path'] ?? '');
    if ($host === 'youtu.be' || str_ends_with($host, '.youtu.be')) {
      $id = trim($path, '/');
      return $id !== '' ? 'https://www.youtube.com/embed/' . rawurlencode($id) : NULL;
    }
    if (self::hostIs($host, 'youtube.com') || self::hostIs($host, 'youtube-nocookie.com')) {
      $query = [];
      parse_str((string) ($parts['query'] ?? ''), $query);
      $id = is_string($query['v'] ?? NULL) ? $query['v'] : '';
      if ($id === '' && preg_match('#/(?:embed|shorts|live)/([^/?&]+)#', $path, $match) === 1) {
        $id = $match[1];
      }
      return $id !== '' ? 'https://www.youtube.com/embed/' . rawurlencode($id) : NULL;
    }
    if (self::hostIs($host, 'vimeo.com')) {
      if (preg_match('#/(?:video/)?(\d+)#', $path, $match) === 1) {
        return 'https://player.vimeo.com/video/' . $match[1];
      }
    }
    return NULL;
  }

  public static function provider(string $url): string {
    $src = self::iframeSrc($url) ?? '';
    return str_contains($src, 'vimeo.com') ? 'vimeo' : 'youtube';
  }

  private static function hostIs(string $host, string $suffix): bool {
    return $host === $suffix || str_ends_with($host, '.' . $suffix);
  }

}
