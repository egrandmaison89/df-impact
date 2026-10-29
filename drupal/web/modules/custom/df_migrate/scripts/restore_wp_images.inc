<?php

/**
 * @file
 * Shared image validation and WP CDN fetch helpers.
 */

declare(strict_types=1);

/**
 * Returns TRUE if bytes look like a real image (not HTML/placeholder).
 */
function df_restore_valid_image(string $data): bool {
  if (strlen($data) < 128) {
    return FALSE;
  }
  if (stripos($data, '<html') !== FALSE || stripos($data, '<!DOCTYPE') !== FALSE) {
    return FALSE;
  }
  $head = substr($data, 0, 16);
  if (str_starts_with($head, "\xFF\xD8\xFF")) {
    return TRUE;
  }
  if (str_starts_with($head, "\x89PNG\r\n\x1a\n")) {
    return TRUE;
  }
  if (str_starts_with($head, 'RIFF') && str_contains(substr($data, 0, 32), 'WEBP')) {
    return TRUE;
  }
  if (str_starts_with($head, 'GIF87a') || str_starts_with($head, 'GIF89a')) {
    return TRUE;
  }
  return FALSE;
}

/**
 * @return array<string, string> basename (lower) => source_url
 */
function df_restore_media_url_index(): array {
  static $index = NULL;
  if ($index !== NULL) {
    return $index;
  }
  $index = [];
  $path = DRUPAL_ROOT . '/modules/custom/df_migrate/data/media.json';
  if (!is_readable($path)) {
    return $index;
  }
  $rows = json_decode((string) file_get_contents($path), TRUE);
  if (!is_array($rows)) {
    return $index;
  }
  foreach ($rows as $row) {
    $url = (string) ($row['source_url'] ?? '');
    $name = (string) ($row['filename'] ?? '');
    if ($url !== '' && $name !== '') {
      $index[strtolower($name)] = $url;
      $stem = pathinfo($name, PATHINFO_FILENAME);
      if ($stem !== '') {
        $index[strtolower($stem)] = $url;
      }
    }
  }
  return $index;
}

function df_restore_fetch(string $basename): ?string {
  $basename = rawurldecode($basename);
  $index = df_restore_media_url_index();
  $key = strtolower($basename);
  $stem = strtolower(pathinfo($basename, PATHINFO_FILENAME));
  $urls = [];
  if (isset($index[$key])) {
    $urls[] = $index[$key];
  }
  if ($stem !== '' && isset($index[$stem])) {
    $urls[] = $index[$stem];
  }
  $encoded = implode('/', array_map('rawurlencode', explode('/', $basename)));
  $urls[] = 'https://danafarberimpact.org/wp-content/uploads/' . $encoded;
  $urls[] = 'https://i0.wp.com/danafarberimpact.org/wp-content/uploads/' . $encoded . '?ssl=1';
  if (!str_contains($basename, '/')) {
    foreach (['2026/08/', '2026/06/', '2025/01/', '2024/06/', '2023/06/', '2023/03/', '2023/02/', '2023/01/', '2022/11/'] as $prefix) {
      $urls[] = 'https://danafarberimpact.org/wp-content/uploads/' . $prefix . rawurlencode($basename);
      $urls[] = 'https://i0.wp.com/danafarberimpact.org/wp-content/uploads/' . $prefix . rawurlencode($basename) . '?ssl=1';
    }
  }
  foreach (array_values(array_unique($urls)) as $url) {
    if (preg_match('#wp-content/uploads/(.+)$#i', $url, $m)) {
      $urls[] = 'https://i0.wp.com/danafarberimpact.org/wp-content/uploads/' . $m[1] . '?ssl=1';
    }
  }
  foreach (array_values(array_unique($urls)) as $url) {
    $data = @file_get_contents($url, FALSE, stream_context_create([
      'http' => ['timeout' => 20, 'user_agent' => 'Drupal/11 DFMigrate/restore'],
    ]));
    if ($data !== FALSE && df_restore_valid_image($data)) {
      return $data;
    }
  }
  return NULL;
}

function df_restore_path_is_image(string $path): bool {
  if (!is_readable($path)) {
    return FALSE;
  }
  $probe = @shell_exec('file -b ' . escapeshellarg($path));
  if (!is_string($probe) || $probe === '') {
    $chunk = file_get_contents($path, FALSE, NULL, 0, 8192);
    return $chunk !== FALSE && df_restore_valid_image($chunk);
  }
  return (bool) preg_match('/^(JPEG|PNG|WebP|GIF|RIFF)/i', trim($probe));
}
