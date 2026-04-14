<?php

namespace Drupal\df_migrate\Plugin\migrate\process;

use Drupal\df_migrate\InlineImageHtmlProcessor;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Downloads inline images in HTML from WordPress URLs and rewrites img src.
 *
 * WordPress served lazy-load markup (placeholder src + data-src). Drupal does
 * not run that JS, so images must use real src pointing at local files.
 *
 * @MigrateProcessPlugin(
 *   id = "df_localize_inline_images"
 * )
 */
class LocalizeInlineImages extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (empty($value) || !is_string($value)) {
      return $value;
    }

    return InlineImageHtmlProcessor::localize($value);
  }

}
