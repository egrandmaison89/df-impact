<?php

declare(strict_types=1);

namespace Drupal\df_editor\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;

/**
 * Issue references for articles, including draft issues for launch editors.
 */
#[EntityReferenceSelection(
  id: 'df_editor:issue_article',
  label: new TranslatableMarkup('Issue (drafts allowed for issue launch editors)'),
  entity_types: ['node'],
  group: 'default',
  weight: 0,
)]
final class IssueArticleReferenceSelection extends DefaultSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);
    if ($this->currentUser->hasPermission('publish issue with articles')) {
      $query->condition('type', 'issue');
      return $query;
    }
    $query->condition('status', NodeInterface::PUBLISHED);
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function validateReferenceableNewEntities(array $entities) {
    $entities = parent::validateReferenceableNewEntities($entities);
    if ($this->currentUser->hasPermission('publish issue with articles')) {
      return array_filter($entities, static function ($entity): bool {
        return $entity instanceof NodeInterface && $entity->bundle() === 'issue';
      });
    }
    return array_filter($entities, static function ($entity): bool {
      return $entity instanceof NodeInterface && $entity->isPublished();
    });
  }

}
