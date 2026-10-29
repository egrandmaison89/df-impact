<?php

declare(strict_types=1);

namespace Drupal\df_editor;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Publishes an issue and all attached articles together.
 */
final class IssuePublisher {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {}

  /**
   * Publishes the issue node and every article referencing it.
   *
   * @return int[] Published article node IDs
   */
  public function publishIssueWithArticles(NodeInterface $issue): array {
    if ($issue->bundle() !== 'issue') {
      throw new \InvalidArgumentException('Expected an issue node.');
    }

    $this->applyPublishedState($issue);
    $published = $this->publishAttachedArticles($issue);
    $this->cacheTagsInvalidator->invalidateTags(['node_list:issue', 'node_list:article']);
    return $published;
  }

  /**
   * Publishes every article assigned to the issue (issue itself unchanged).
   *
   * @return int[]
   */
  public function publishAttachedArticles(NodeInterface $issue): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $article_nids = $storage->getQuery()
      ->condition('type', 'article')
      ->condition('field_issue.target_id', $issue->id())
      ->accessCheck(FALSE)
      ->execute();

    $published = [];
    foreach ($storage->loadMultiple($article_nids) as $article) {
      if (!$article instanceof NodeInterface) {
        continue;
      }
      $this->applyPublishedState($article);
      $published[] = (int) $article->id();
    }
    return $published;
  }

  private function applyPublishedState(NodeInterface $node): void {
    if ($node->hasField('moderation_state')) {
      $node->set('moderation_state', 'published');
    }
    $node->setPublished(TRUE);
    if ($node->hasField('publish_on') && !$node->get('publish_on')->isEmpty()) {
      $node->set('publish_on', NULL);
    }
    $node->setChangedTime($this->time->getRequestTime());
    $node->save();
  }

}
