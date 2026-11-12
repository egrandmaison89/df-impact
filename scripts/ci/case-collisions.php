<?php

declare(strict_types=1);

/**
 * Fails when two tracked paths or file_managed URIs differ only by case.
 *
 * Case-insensitive disks (macOS) keep one of the pair, so a story can render
 * the other photo. The comparison is the full path or URI, not the basename
 * across different directories.
 */

$root = dirname(__DIR__, 2);
$failed = FALSE;

$tracked = [];
exec('git -C ' . escapeshellarg($root) . ' ls-files', $tracked, $code);
if ($code !== 0) {
  fwrite(STDERR, "git ls-files failed\n");
  exit(1);
}

$file_collisions = case_collisions($tracked);
echo 'Tracked files: ' . count($tracked) . "\n";
echo 'Tracked case collisions: ' . count($file_collisions) . "\n";
foreach ($file_collisions as $group) {
  $failed = TRUE;
  echo "tracked:\n";
  foreach ($group as $path) {
    echo "  {$path}\n";
  }
}

$uris = file_managed_uris();
$uri_collisions = case_collisions($uris);
echo 'file_managed URIs: ' . count($uris) . "\n";
echo 'file_managed case collisions: ' . count($uri_collisions) . "\n";
foreach ($uri_collisions as $group) {
  $failed = TRUE;
  echo "file_managed:\n";
  foreach ($group as $uri) {
    echo "  {$uri}\n";
  }
}

exit($failed ? 1 : 0);

/**
 * @param list<string> $values
 * @return list<list<string>>
 */
function case_collisions(array $values): array {
  $groups = [];
  foreach ($values as $value) {
    if ($value === '') {
      continue;
    }
    $groups[mb_strtolower($value, 'UTF-8')][] = $value;
  }
  $collisions = [];
  foreach ($groups as $items) {
    $unique = array_values(array_unique($items));
    if (count($unique) > 1) {
      sort($unique, SORT_STRING);
      $collisions[] = $unique;
    }
  }
  return $collisions;
}

/**
 * @return list<string>
 */
function file_managed_uris(): array {
  $host = getenv('DF_DB_HOST') ?: '127.0.0.1';
  $name = getenv('DF_DB_NAME') ?: 'dfimpact';
  $user = getenv('DF_DB_USER') ?: 'dfimpact';
  $pass = getenv('DF_DB_PASS') ?: 'dfimpact';
  $port = getenv('DF_DB_PORT') ?: '3306';
  try {
    $pdo = new PDO(
      "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
      $user,
      $pass,
      [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
  }
  catch (PDOException $exception) {
    fwrite(STDERR, 'Could not read file_managed: ' . $exception->getMessage() . "\n");
    exit(1);
  }
  $exists = $pdo->query("SHOW TABLES LIKE 'file_managed'")->fetchColumn();
  if ($exists === FALSE) {
    fwrite(STDERR, "file_managed is missing\n");
    exit(1);
  }
  $uris = [];
  foreach ($pdo->query('SELECT uri FROM file_managed') as $row) {
    if (isset($row['uri']) && is_string($row['uri']) && $row['uri'] !== '') {
      $uris[] = $row['uri'];
    }
  }
  return $uris;
}
