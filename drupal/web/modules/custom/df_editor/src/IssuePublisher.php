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

  /** Invalidates chrome that lists the newest published issue (header, home, browse). */
  public const CACHE_TAG_ISSUE_NAV = 'df_editor:issue_navigation';

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
    $this->cacheTagsInvalidator->invalidateTags([
      'node_list:issue',
      'node_list:article',
      self::CACHE_TAG_ISSUE_NAV,
      'config:df_editor.settings',
      'node:' . $issue->id(),
    ]);
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

    $articles = [];
    foreach ($storage->loadMultiple($article_nids) as $article) {
      if ($article instanceof NodeInterface) {
        $articles[] = $article;
      }
    }
    usort($articles, static fn (NodeInterface $a, NodeInterface $b): int => (int) $a->getCreatedTime() <=> (int) $b->getCreatedTime());

    $homepage_weight = 1;
    $postx_weight = 1;
    $published = [];
    foreach ($articles as $article) {
      $this->applyEditorListingWeights($article, $homepage_weight, $postx_weight);
      $this->applyPublishedState($article);
      $published[] = (int) $article->id();
    }
    return $published;
  }

  /**
   * Assigns homepage/issue listing weights when editors publish without admin tuning.
   */
  private function applyEditorListingWeights(NodeInterface $article, int &$homepage_weight, int &$postx_weight): void {
    if ($article->hasField('field_homepage_sort_weight')) {
      $current = $article->get('field_homepage_sort_weight')->value;
      if ($current === NULL || $current === '' || (int) $current === 0) {
        $article->set('field_homepage_sort_weight', $homepage_weight);
        $homepage_weight++;
      }
    }
    if ($article->hasField('field_postx_sort_weight')) {
      $current = $article->get('field_postx_sort_weight')->value;
      if ($current === NULL || $current === '' || (int) $current === 0) {
        $article->set('field_postx_sort_weight', $postx_weight);
        $postx_weight++;
      }
    }
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
