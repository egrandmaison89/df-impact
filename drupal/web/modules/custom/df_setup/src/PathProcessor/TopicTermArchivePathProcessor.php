<?php

declare(strict_types=1);

namespace Drupal\df_setup\PathProcessor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Points public taxonomy term URLs to the topic archive (WP /category/[name]).
 */
final class TopicTermArchivePathProcessor implements OutboundPathProcessorInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL): string {
    if (!preg_match('#^/taxonomy/term/(\d+)$#', $path, $matches)) {
      return $path;
    }
    $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($matches[1]);
    if (!$term instanceof TermInterface || $term->bundle() !== 'topics' || !$term->isPublished()) {
      return $path;
    }
    if ($bubbleable_metadata) {
      $bubbleable_metadata->addCacheableDependency($term);
    }
    return '/category/' . df_setup_topic_category_slug($term->getName());
  }

}
