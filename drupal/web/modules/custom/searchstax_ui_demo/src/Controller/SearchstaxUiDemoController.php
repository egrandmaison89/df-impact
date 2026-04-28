<?php

declare(strict_types=1);

namespace Drupal\searchstax_ui_demo\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Serves a page that loads SearchStax Studio JS against public demo endpoints.
 */
final class SearchstaxUiDemoController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function content(): array {
    $build = [
      '#theme' => 'searchstax_ui_demo_page',
      '#attached' => [
        'library' => [
          'searchstax_ui_demo/searchstax_ui_demo',
        ],
      ],
    ];

    return $build;
  }

}
