<?php

declare(strict_types=1);

/**
 * Detects HTML captcha pages saved with image extensions.
 *
 * Fails when a git-tracked file is HTML. Prints runtime wp-images stubs
 * without failing, because a fresh checkout does not contain that directory
 * and live WordPress may answer image requests with a captcha page.
 */

$root = dirname(__DIR__, 2);
$failed = FALSE;

$tracked = [];
exec('git -C ' . escapeshellarg($root) . ' ls-files -- drupal/web/themes/custom drupal/web/modules/custom drupal/web/sites/default/files', $tracked, $code);
if ($code !== 0) {
  fwrite(STDERR, "git ls-files failed\n");
  exit(1);
}
foreach ($tracked as $relative) {
  if (!is_string($relative) || !preg_match('/\.(?:jpe?g|png|gif|webp)$/i', $relative)) {
    continue;
  }
  $path = $root . '/' . $relative;
  if (!is_file($path) || is_image($path)) {
    continue;
  }
  fwrite(STDERR, "HTML saved as an image: {$relative}\n");
  $failed = TRUE;
}

$runtime = $root . '/drupal/web/sites/default/files/wp-images';
$stubs = [];
if (is_dir($runtime)) {
  foreach (scandir($runtime) ?: [] as $name) {
    if ($name === '.' || $name === '..') {
      continue;
    }
    $path = $runtime . '/' . $name;
    if (!is_file($path) || is_image($path)) {
      continue;
    }
    $stubs[] = $name;
  }
}

echo 'Tracked HTML-as-image files: ' . ($failed ? 'found' : 'none') . "\n";
echo 'Runtime wp-images non-image files: ' . count($stubs) . "\n";
foreach (array_slice($stubs, 0, 40) as $name) {
  echo "  {$name}\n";
}
if (count($stubs) > 40) {
  echo '  … ' . (count($stubs) - 40) . " more\n";
}

if ($failed) {
  exit(1);
}

exit(0);

function is_image(string $path): bool {
  $handle = fopen($path, 'rb');
  if ($handle === FALSE) {
    return FALSE;
  }
  $magic = fread($handle, 16) ?: '';
  fclose($handle);
  if ($magic === '' || $magic[0] === '<') {
    return FALSE;
  }
  if (str_starts_with($magic, "\xFF\xD8") || str_starts_with($magic, "\x89PNG\r\n\x1a\n")) {
    return TRUE;
  }
  if (str_starts_with($magic, 'GIF87a') || str_starts_with($magic, 'GIF89a')) {
    return TRUE;
  }
  return str_starts_with($magic, 'RIFF') && str_contains($magic, 'WEBP');
}
