<?php

/**
 * @file
 * Backfill field_related_articles from wp_jetpack_related_posts.json.
 */

use Drupal\node\Entity\Node;

/**
 * @return array<int, int>
 */
/**
 * @return array<string, int>
 */
function df_migrate_article_slug_to_wp_id_map(): array {
  static $map = NULL;
  if ($map !== NULL) {
    return $map;
  }
  $map = [];
  foreach (['articles.json', 'in_brief.json'] as $file) {
    $path = __DIR__ . '/../data/' . $file;
    if (!is_readable($path)) {
      continue;
    }
    $rows = json_decode((string) file_get_contents($path), TRUE);
    if (!is_array($rows)) {
      continue;
    }
    foreach ($rows as $row) {
      if (!empty($row['slug']) && !empty($row['wp_id'])) {
        $map[(string) $row['slug']] = (int) $row['wp_id'];
      }
    }
  }
  return $map;
}

/**
 * @return array<int, int>
 */
function df_migrate_wp_id_to_article_nid_map(): array {
  static $map = NULL;
  if ($map !== NULL) {
    return $map;
  }
  $map = [];
  foreach (['migrate_map_df_articles', 'migrate_map_df_in_brief'] as $table) {
    if (!\Drupal::database()->schema()->tableExists($table)) {
      continue;
    }
    $rows = \Drupal::database()->select($table, 'm')
      ->fields('m', ['sourceid1', 'destid1'])
      ->execute();
    foreach ($rows as $row) {
      $map[(int) $row->sourceid1] = (int) $row->destid1;
    }
  }
  return $map;
}

/**
 * @return array<string, mixed>|null
 */
function df_migrate_load_jetpack_related_json(): ?array {
  $path = __DIR__ . '/../data/wp_jetpack_related_posts.json';
  if (!is_readable($path)) {
    return NULL;
  }
  $data = json_decode((string) file_get_contents($path), TRUE);
  return is_array($data) ? $data : NULL;
}

/**
 * Run Jetpack related-articles backfill.
 */
function df_migrate_backfill_jetpack_related_run(bool $dry_run = FALSE, ?int $only_wp_id = NULL): void {
  $data = df_migrate_load_jetpack_related_json();
  if ($data === NULL) {
    throw new \RuntimeException('Missing wp_jetpack_related_posts.json — run parity/scripts/fetch-wp-jetpack-related.mjs');
  }
  $by_wp_id = $data['by_wp_id'] ?? [];
  if (!is_array($by_wp_id) || $by_wp_id === []) {
    throw new \RuntimeException('wp_jetpack_related_posts.json has empty by_wp_id');
  }
  $wp_to_nid = df_migrate_wp_id_to_article_nid_map();
  $slug_to_wp = df_migrate_article_slug_to_wp_id_map();
  $updated = 0;
  $skipped = 0;

  foreach ($by_wp_id as $entry) {
    if (!is_array($entry)) {
      continue;
    }
    $source_wp_id = (int) ($entry['wp_id'] ?? 0);
    if ($only_wp_id !== NULL && $source_wp_id !== $only_wp_id) {
      continue;
    }
    $nid = $wp_to_nid[$source_wp_id] ?? NULL;
    if ($nid === NULL) {
      $skipped++;
      continue;
    }
    $node = Node::load($nid);
    if (!$node || $node->bundle() !== 'article') {
      $skipped++;
      continue;
    }
    $target_nids = [];
    foreach ($entry['related'] ?? [] as $rel) {
      if (!is_array($rel)) {
        continue;
      }
      $rel_wp = (int) ($rel['wp_id'] ?? 0);
      if ($rel_wp <= 0 && !empty($rel['slug'])) {
        $rel_wp = (int) ($slug_to_wp[(string) $rel['slug']] ?? 0);
      }
      if ($rel_wp <= 0 || $rel_wp === $source_wp_id) {
        continue;
      }
      $rel_nid = $wp_to_nid[$rel_wp] ?? NULL;
      if ($rel_nid !== NULL && !in_array($rel_nid, $target_nids, TRUE)) {
        $target_nids[] = $rel_nid;
      }
    }
    $target_nids = array_slice($target_nids, 0, 3);
    $values = array_map(static fn (int $id) => ['target_id' => $id], $target_nids);
    if (!$dry_run) {
      $node->set('field_related_articles', $values);
      $node->save();
    }
    $updated++;
    \Drupal::logger('df_migrate')->notice('Jetpack related wp_id=@w nid=@n -> @refs', [
      '@w' => $source_wp_id,
      '@n' => $nid,
      '@refs' => implode(',', $target_nids),
    ]);
  }

  echo "Jetpack related backfill: updated=$updated skipped=$skipped dry_run=" . ($dry_run ? 'yes' : 'no') . "\n";
}
