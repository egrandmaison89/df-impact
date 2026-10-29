<?php

declare(strict_types=1);

namespace Drupal\df_setup\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\df_setup\ArchivePageWpOrder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves WordPress /tag/{slug} archives using frozen WP order + topic_archive view.
 */
final class WpTagArchiveController extends ControllerBase {

  /**
   * Renders a tag archive (optional WP-style /page/N).
   */
  public function render(string $slug, Request $request, ?int $page = NULL): array {
    $wp_page = $page ?? max(1, (int) $request->query->get('page', 0) + 1);
    $tag_path = '/tag/' . $slug . ($wp_page > 1 ? '/page/' . $wp_page : '');
    if (ArchivePageWpOrder::pathsForArchive($tag_path) === []) {
      throw new NotFoundHttpException();
    }
    $request->attributes->set('df_setup_wp_archive_path', $tag_path);
    if ($wp_page > 1) {
      $request->query->set('page', (string) ($wp_page - 1));
    }
    $build = views_embed_view('topic_archive', 'page_1', 'all');
    if (!is_array($build)) {
      throw new NotFoundHttpException();
    }
    return $build;
  }

  /**
   * Page title for tag archives (WP uses tag display name).
   */
  public function title(string $slug): string {
    return ucwords(str_replace('-', ' ', $slug));
  }

}
