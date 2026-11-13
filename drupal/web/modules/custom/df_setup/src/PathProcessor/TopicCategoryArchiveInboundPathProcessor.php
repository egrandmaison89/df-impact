<?php

declare(strict_types=1);

namespace Drupal\df_setup\PathProcessor;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Rewrites WordPress-style category pagination paths for Views.
 *
 * WP: /category/{slug}/page/2/ → Drupal view: /category/{slug}?page=1
 */
final class TopicCategoryArchiveInboundPathProcessor implements InboundPathProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request): string {
    if (!preg_match('#^/category/([^/]+)/page/(\d+)/?$#', $path, $matches)) {
      return $path;
    }
    $slug = $matches[1];
    $wp_page = max(1, (int) $matches[2]);
    $drupal_page = $wp_page - 1;
    $query = $request->query->all();
    $query['page'] = (string) $drupal_page;
    $request->query->replace($query);
    $request->attributes->set(
      'df_setup_wp_archive_path',
      '/category/' . $slug . '/page/' . $wp_page,
    );
    return '/category/' . $slug;
  }

}
