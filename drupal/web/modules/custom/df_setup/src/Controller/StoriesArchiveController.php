<?php

declare(strict_types=1);

namespace Drupal\df_setup\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * WordPress /stories archive parity (same content as /issues view).
 */
final class StoriesArchiveController extends ControllerBase {

  /**
   * Renders the stories archive at the WP path /stories.
   */
  public function build(): array {
    $build = views_embed_view('stories_archive', 'page_1');
    if (!is_array($build)) {
      throw new NotFoundHttpException();
    }
    return $build;
  }

}
