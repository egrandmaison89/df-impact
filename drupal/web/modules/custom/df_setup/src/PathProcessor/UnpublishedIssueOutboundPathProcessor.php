<?php

declare(strict_types=1);

namespace Drupal\df_setup\PathProcessor;

use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\df_setup\UnpublishedIssueLinks;
use Symfony\Component\HttpFoundation\Request;

/**
 * Rewrites generated links to an unpublished issue for anonymous visitors.
 */
final class UnpublishedIssueOutboundPathProcessor implements OutboundPathProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL) {
    if ($bubbleable_metadata) {
      $bubbleable_metadata->addCacheContexts(['user.permissions']);
    }
    if (!is_string($path) || $path === '') {
      return $path;
    }
    if (UnpublishedIssueLinks::accountCanView(\Drupal::currentUser())) {
      return $path;
    }
    if (!UnpublishedIssueLinks::isUnpublishedIssuePath($path)) {
      return $path;
    }
    return '/browse-issues';
  }

}
