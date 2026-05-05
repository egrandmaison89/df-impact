<?php

namespace Drupal\df_migrate\Plugin\migrate\process;

use Drupal\df_setup\ArticleBodyCleaner;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Strips WP issue/byline decks and rewrites legacy internal links.
 *
 * @MigrateProcessPlugin(
 *   id = "df_clean_article_body"
 * )
 */
class CleanArticleBody extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ArticleBodyCleaner $articleBodyCleaner,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('df_setup.article_body_cleaner'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (empty($value) || !is_string($value)) {
      return $value;
    }

    $featuredUrl = $row->getSourceProperty('featured_image_url');
    $featuredUrl = is_string($featuredUrl) && $featuredUrl !== '' ? $featuredUrl : NULL;

    $result = $this->articleBodyCleaner->cleanArticleHtml($value, $featuredUrl);
    return $result['html'];
  }

}
