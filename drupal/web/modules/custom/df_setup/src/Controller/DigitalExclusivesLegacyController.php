<?php

declare(strict_types=1);

namespace Drupal\df_setup\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Serves /digital-exclusives (same content as /category/digital-exclusives).
 */
final class DigitalExclusivesLegacyController extends ControllerBase {

  /**
   * Renders the digital exclusives archive view (page_1).
   */
  public function render(Request $request): array {
    $request->attributes->set('df_setup_wp_archive_path', '/digital-exclusives');
    $build = views_embed_view('digital_exclusives_archive', 'page_1');
    return is_array($build) ? $build : ['#markup' => ''];
  }

}
