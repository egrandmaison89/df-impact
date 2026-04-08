<?php

namespace Drupal\df_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Maps an array of taxonomy term names to an array of target_id references.
 *
 * Usage:
 * @code
 * field_topics:
 *   plugin: df_map_term_names
 *   source: topics
 *   vocabulary: topics
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "df_map_term_names",
 *   handle_multiples = TRUE
 * )
 */
class MapTermNames extends ProcessPluginBase {

  /**
   * Static cache of term name -> tid lookups per vocabulary.
   */
  protected static array $termCache = [];

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (empty($value) || !is_array($value)) {
      return [];
    }

    $vocabulary = $this->configuration['vocabulary'] ?? 'topics';
    $result = [];

    foreach ($value as $term_name) {
      $term_name = trim($term_name);
      if (empty($term_name)) {
        continue;
      }

      $tid = $this->findTermByName($term_name, $vocabulary);
      if ($tid) {
        $result[] = ['target_id' => $tid];
      }
      else {
        \Drupal::logger('df_migrate')->warning(
          'Term "@name" not found in vocabulary @vocab',
          ['@name' => $term_name, '@vocab' => $vocabulary]
        );
      }
    }

    return $result;
  }

  /**
   * Finds a taxonomy term ID by name within a vocabulary.
   */
  protected function findTermByName(string $name, string $vocabulary): ?int {
    $cache_key = $vocabulary . ':' . $name;
    if (isset(static::$termCache[$cache_key])) {
      return static::$termCache[$cache_key];
    }

    $tids = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid', $vocabulary)
      ->condition('name', $name)
      ->accessCheck(FALSE)
      ->execute();

    $tid = !empty($tids) ? (int) reset($tids) : NULL;
    static::$termCache[$cache_key] = $tid;

    return $tid;
  }

}
