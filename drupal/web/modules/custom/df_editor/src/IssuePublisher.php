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
   * Publishes the issue plus linked Draft and In Review articles and briefs.
   *
   * Homepage placement and listing weights are left as the editor set them.
   * A blank placement stays blank.
   *
   * @return int[]
   *   Node IDs that were transitioned to published.
   */
  public function publishIssueWithArticles(NodeInterface $issue): array {
    if ($issue->bundle() !== 'issue') {
      throw new \InvalidArgumentException('Expected an issue node.');
    }

    $this->applyPublishedState($issue);
    $published = $this->publishAttachedArticles($issue);
    $this->invalidateIssueCaches($issue);
    return $published;
  }

  /**
   * Publishes Draft and In Review articles and briefs assigned to the issue.
   *
   * Does not assign homepage weights or placement.
   *
   * @return int[]
   */
  public function publishAttachedArticles(NodeInterface $issue): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $nids = $storage->getQuery()
      ->condition('type', ['article', 'in_brief'], 'IN')
      ->condition('field_issue.target_id', $issue->id())
      ->accessCheck(FALSE)
      ->execute();

    $nodes = [];
    foreach ($storage->loadMultiple($nids) as $node) {
      if ($node instanceof NodeInterface && $this->isDraftOrInReview($node)) {
        $nodes[] = $node;
      }
    }
    usort($nodes, static fn (NodeInterface $a, NodeInterface $b): int => (int) $a->getCreatedTime() <=> (int) $b->getCreatedTime());

    $published = [];
    foreach ($nodes as $node) {
      $this->applyPublishedState($node);
      $published[] = (int) $node->id();
    }
    $this->invalidateIssueCaches($issue);
    return $published;
  }

  /**
   * Draft and In Review only. Published and archived nodes are left alone.
   */
  private function isDraftOrInReview(NodeInterface $node): bool {
    if (!$node->hasField('moderation_state')) {
      return !$node->isPublished();
    }
    return in_array((string) $node->get('moderation_state')->value, ['draft', 'in_review'], TRUE);
  }

  private function invalidateIssueCaches(NodeInterface $issue): void {
    $this->cacheTagsInvalidator->invalidateTags([
      'node_list:issue',
      'node_list:article',
      'node_list:in_brief',
      self::CACHE_TAG_ISSUE_NAV,
      'config:df_editor.settings',
      'config:views.view.browse_issues',
      'config:views.view.issue_articles',
      'node:' . $issue->id(),
    ]);
  }

  private function applyPublishedState(NodeInterface $node): void {
    $homepage_was_blank = $node->hasField('field_homepage_sort_weight') && $node->get('field_homepage_sort_weight')->isEmpty();
    if ($node->hasField('moderation_state')) {
      $node->set('moderation_state', 'published');
    }
    $node->setPublished(TRUE);
    if ($node->hasField('publish_on') && !$node->get('publish_on')->isEmpty()) {
      $node->set('publish_on', NULL);
    }
    $node->setChangedTime($this->time->getRequestTime());
    $node->save();
    $this->clearInventedHomepageWeight($node, $homepage_was_blank);
  }

  /**
   * Publish must not fill homepage slots 1–5. A blank weight stays blank.
   */
  private function clearInventedHomepageWeight(NodeInterface $node, bool $homepage_was_blank): void {
    if (!$homepage_was_blank || !$node->id() || !$node->hasField('field_homepage_sort_weight')) {
      return;
    }
    $storage = $this->entityTypeManager->getStorage('node');
    $storage->resetCache([(int) $node->id()]);
    $fresh = $storage->load($node->id());
    if (!$fresh instanceof NodeInterface || $fresh->get('field_homepage_sort_weight')->isEmpty()) {
      return;
    }
    $weight = (int) $fresh->get('field_homepage_sort_weight')->value;
    if ($weight < 1 || $weight > 5) {
      return;
    }
    $fresh->set('field_homepage_sort_weight', NULL);
    $fresh->save();
  }

}
