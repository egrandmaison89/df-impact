<?php

namespace Drupal\df_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\node\Entity\Node;

/**
 * Maps a WordPress issue name (e.g. "Spring 2026") to a Drupal Issue node ID.
 *
 * Usage:
 * @code
 * field_issue:
 *   plugin: df_map_issue_name
 *   source: issue_name
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "df_map_issue_name"
 * )
 */
class MapIssueName extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (empty($value)) {
      return NULL;
    }

    // Parse season and year from the issue name (e.g. "Spring 2026")
    if (!preg_match('/^(spring|summer|fall|winter|late fall)\s+(\d{4})$/i', $value, $matches)) {
      return NULL;
    }

    $season = strtolower($matches[1]);
    $year = (int) $matches[2];

    // Look up the Issue node by season + year fields
    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'issue')
      ->condition('field_season', $season)
      ->condition('field_year', $year)
      ->accessCheck(FALSE)
      ->execute();

    if (empty($nids)) {
      // Issue not found — log warning and skip
      \Drupal::logger('df_migrate')->warning(
        'Issue node not found for @season @year',
        ['@season' => $season, '@year' => $year]
      );
      return NULL;
    }

    return ['target_id' => reset($nids)];
  }

}
