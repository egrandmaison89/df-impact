<?php

declare(strict_types=1);

namespace Drupal\df_editor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Resolves "Current Issue" navigation to the latest published issue URL.
 */
final class CurrentIssueNav {

  use StringTranslationTrait;

  public const DEFAULT_LABEL = 'Current Issue';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Canonical URL string for the newest published issue, or NULL.
   */
  public function latestIssueUrl(): ?string {
    $nid = function_exists('df_setup_get_latest_issue_nid') ? df_setup_get_latest_issue_nid() : NULL;
    if ($nid === NULL) {
      return NULL;
    }
    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    if (!$node instanceof NodeInterface) {
      return NULL;
    }
    return $node->toUrl()->toString();
  }

  /**
   * Applies dynamic Current Issue URL to header link rows.
   *
   * @param array<int, array{title: string, url: string}> $links
   *
   * @return array<int, array{title: string, url: string}>
   */
  public function applyCurrentIssueUrl(array $links, ?string $match_title = NULL): array {
    $match = mb_strtolower(trim($match_title ?? self::DEFAULT_LABEL), 'UTF-8');
    $latest = $this->latestIssueUrl();
    if ($latest === NULL) {
      return $links;
    }
    foreach ($links as &$link) {
      $title = mb_strtolower(trim((string) ($link['title'] ?? '')), 'UTF-8');
      if ($title === $match || $title === 'current issue') {
        $link['url'] = $latest;
      }
    }
    return $links;
  }

  /**
   * Edit form URL for the featured issue homepage hero field group.
   */
  public function homepageHeroEditUrl(): ?Url {
    $nid = function_exists('df_setup_get_latest_issue_nid') ? df_setup_get_latest_issue_nid() : NULL;
    if ($nid === NULL) {
      return NULL;
    }
    return Url::fromRoute('entity.node.edit_form', ['node' => $nid], [
      'fragment' => 'edit-df-homepage-hero',
    ]);
  }

}
