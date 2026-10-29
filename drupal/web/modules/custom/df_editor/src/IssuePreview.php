<?php

declare(strict_types=1);

namespace Drupal\df_editor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Treats a draft issue as "latest" for nav/homepage preview (editors only).
 */
final class IssuePreview {

  public const QUERY_PARAMETER = 'df_preview_issue';

  public const SESSION_KEY = 'df_editor_preview_issue_nid';

  public function __construct(
    private readonly RequestStack $requestStack,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Issue NID to treat as featured, or NULL for live site behavior.
   */
  public function getPreviewIssueNid(): ?int {
    $request = $this->requestStack->getCurrentRequest();
    if ($request === NULL) {
      return NULL;
    }
    $candidate = $request->query->get(self::QUERY_PARAMETER);
    if ($candidate === NULL || $candidate === '') {
      $candidate = $request->getSession()->get(self::SESSION_KEY);
    }
    if ($candidate === NULL || $candidate === '') {
      return NULL;
    }
    $nid = (int) $candidate;
    if ($nid <= 0) {
      return NULL;
    }
    if (!$this->currentUser->hasPermission('publish issue with articles')) {
      return NULL;
    }
    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'issue') {
      return NULL;
    }
    if (!$node->access('update', $this->currentUser)) {
      return NULL;
    }
    return $nid;
  }

  /**
   * Persist preview selection for subsequent requests (e.g. new tab to front page).
   */
  public function setPreviewIssueNid(int $nid): void {
    $request = $this->requestStack->getCurrentRequest();
    if ($request !== NULL) {
      $request->getSession()->set(self::SESSION_KEY, $nid);
    }
  }

  public function clearPreview(): void {
    $request = $this->requestStack->getCurrentRequest();
    if ($request !== NULL) {
      $request->getSession()->remove(self::SESSION_KEY);
    }
  }

  /**
   * Front-page preview URL for an issue the current user may edit.
   */
  public function frontPreviewUrl(NodeInterface $issue): ?string {
    if ($issue->bundle() !== 'issue' || !$issue->access('update', $this->currentUser)) {
      return NULL;
    }
    return Url::fromRoute('<front>', [], [
      'query' => [self::QUERY_PARAMETER => $issue->id()],
    ])->toString();
  }

}
