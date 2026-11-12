<?php

declare(strict_types=1);

namespace Drupal\df_secondary\Plugin\search_api\processor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\df_secondary\NodePublicationTime;
use Drupal\node\NodeInterface;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\SearchApiException;

/**
 * Indexes a publication timestamp used to sort blank search.
 *
 * The property is defined on the item (no datasource) so Views data can load
 * the field during cache rebuilds.
 */
#[SearchApiProcessor(
  id: 'publication_sort',
  label: new TranslatableMarkup('Publication sort'),
  description: new TranslatableMarkup('Adds a publication timestamp for blank-search ordering.'),
  stages: [
    'add_properties' => 0,
  ],
  locked: TRUE,
  hidden: TRUE,
)]
class PublicationSort extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    $properties = [];
    if (!$datasource) {
      $properties['publication_sort'] = new ProcessorProperty([
        'label' => $this->t('Publication sort'),
        'description' => $this->t('WordPress publication time, or node changed/created.'),
        'type' => 'integer',
        'processor_id' => $this->getPluginId(),
        'is_list' => FALSE,
      ]);
    }
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item): void {
    try {
      $entity = $item->getOriginalObject()->getValue();
    }
    catch (SearchApiException) {
      return;
    }
    if (!$entity instanceof NodeInterface) {
      return;
    }
    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), NULL, 'publication_sort');
    foreach ($fields as $field) {
      $field->addValue(NodePublicationTime::sortValue($entity));
    }
  }

}
