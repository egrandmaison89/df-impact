<?php

declare(strict_types=1);

namespace Drupal\df_migrate\Plugin\migrate\process;

use Drupal\df_migrate\TextEntityHelper;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Decodes HTML character references in plain-text migrate columns.
 *
 * @MigrateProcessPlugin(
 *   id = "df_decode_plain_text"
 * )
 */
final class DecodePlainText extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!is_scalar($value)) {
      return $value;
    }
    return TextEntityHelper::decode((string) $value);
  }

}
