<?php

declare(strict_types=1);

namespace Drupal\df_secondary;

use Drupal\node\NodeInterface;

/**
 * WordPress-aligned publication time for search ordering.
 *
 * Migrated nodes share the same Drupal created date (bulk import). Blank search
 * uses the export manifest post date when available, then changed, then created.
 */
final class NodePublicationTime {

  /**
   * @var array<int, array<string, mixed>>|null
   */
  private static ?array $articleRowsByNid = NULL;

  /**
   * Sortable Unix timestamp for Search API (newest first).
   */
  public static function sortValue(NodeInterface $node): int {
    if ($node->bundle() === 'article') {
      $manifest = self::articleManifestRow($node);
      if (is_array($manifest)) {
        $fromCreated = self::parseIsoDate((string) ($manifest['created'] ?? ''));
        if ($fromCreated !== NULL) {
          return $fromCreated;
        }
      }
    }
    if ($node->bundle() === 'issue') {
      return self::issuePublicationTime($node);
    }
    return self::fallbackNodeTime($node);
  }

  /**
   * Parses export JSON created/changed strings (WordPress post date).
   */
  public static function parseIsoDate(string $value): ?int {
    $value = trim($value);
    if ($value === '') {
      return NULL;
    }
    $timestamp = strtotime($value);
    return $timestamp !== FALSE ? $timestamp : NULL;
  }

  /**
   * Approximate magazine issue release time from season and year fields.
   */
  public static function issuePublicationTime(NodeInterface $issue): int {
    if ($issue->bundle() !== 'issue') {
      return self::fallbackNodeTime($issue);
    }
    $year = (int) ($issue->get('field_year')->value ?? 0);
    if ($year < 1970) {
      return self::fallbackNodeTime($issue);
    }
    $season = mb_strtolower(trim((string) ($issue->get('field_season')->value ?? '')), 'UTF-8');
    $month = match ($season) {
      'spring' => 3,
      'summer' => 6,
      'fall', 'autumn' => 9,
      'winter' => 12,
      'late fall' => 11,
      default => 6,
    };
    $calendarYear = $season === 'winter' ? $year + 1 : $year;
    return (int) mktime(12, 0, 0, $month, 15, $calendarYear);
  }

  /**
   * @return array<string, mixed>|null
   */
  public static function articleManifestRow(NodeInterface $node): ?array {
    if ($node->bundle() !== 'article' || !$node->id()) {
      return NULL;
    }
    $map = self::articleRowsByNid();
    return $map[(int) $node->id()] ?? NULL;
  }

  public static function fallbackNodeTime(NodeInterface $node): int {
    $changed = (int) $node->getChangedTime();
    if ($changed > 0) {
      return $changed;
    }
    $created = (int) $node->getCreatedTime();
    if ($created > 0) {
      return $created;
    }
    return (int) $node->id();
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  private static function articleRowsByNid(): array {
    if (self::$articleRowsByNid !== NULL) {
      return self::$articleRowsByNid;
    }
    self::$articleRowsByNid = [];
    $path = self::exportDataPath('articles.json');
    if ($path === NULL) {
      return self::$articleRowsByNid;
    }
    $rows = json_decode((string) file_get_contents($path), TRUE);
    if (!is_array($rows)) {
      return self::$articleRowsByNid;
    }
    $wpToNid = self::migrateMap('migrate_map_df_articles');
    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      $nid = self::resolveNidFromRow($row, $wpToNid);
      if ($nid !== NULL) {
        self::$articleRowsByNid[$nid] = $row;
      }
    }
    return self::$articleRowsByNid;
  }

  /**
   * @param array<string, mixed> $row
   * @param array<int|string, int> $wpToNid
   */
  private static function resolveNidFromRow(array $row, array $wpToNid): ?int {
    $wpId = (int) ($row['wp_id'] ?? 0);
    if ($wpId && isset($wpToNid[$wpId])) {
      return (int) $wpToNid[$wpId];
    }
    $alias = $row['drupal_alias'] ?? '';
    if (!is_string($alias) || $alias === '') {
      return NULL;
    }
  if (!\Drupal::hasService('path_alias.manager')) {
    return NULL;
  }
    $alias = '/' . ltrim($alias, '/');
    $path_alias = \Drupal::service('path_alias.manager');
    foreach ([NULL, 'en'] as $langcode) {
      $internal = $langcode === NULL
        ? $path_alias->getPathByAlias($alias)
        : $path_alias->getPathByAlias($alias, $langcode);
      if (preg_match('#^/node/(\d+)$#', (string) $internal, $matches)) {
        return (int) $matches[1];
      }
    }
    return NULL;
  }

  /**
   * @return array<int|string, int>
   */
  private static function migrateMap(string $table): array {
    if (!\Drupal::hasService('database')) {
      return [];
    }
    /** @var \Drupal\Core\Database\Connection $database */
    $database = \Drupal::database();
    if (!$database->schema()->tableExists($table)) {
      return [];
    }
    $map = $database->select($table, 'm')
      ->fields('m', ['sourceid1', 'destid1'])
      ->execute()
      ->fetchAllKeyed();
    return array_map('intval', $map);
  }

  private static function exportDataPath(string $filename): ?string {
    if (!\Drupal::hasService('extension.list.module')) {
      return NULL;
    }
    /** @var \Drupal\Core\Extension\ModuleExtensionList $list */
    $list = \Drupal::service('extension.list.module');
    $relative = $list->getPath('df_setup') . '/data/' . $filename;
    $absolute = DRUPAL_ROOT . '/' . $relative;
    return is_readable($absolute) ? $absolute : NULL;
  }

}
