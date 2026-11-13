<?php

declare(strict_types=1);

namespace Drupal\df_setup\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * WordPress /topics parity: live WP returns a 404 page at this path.
 */
final class TopicsIndexController extends ControllerBase {

  /**
   * Builds the same not-found main content WordPress serves at /topics/.
   */
  public function build(): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['wp-block-group', 'is-layout-flow']],
      'main' => [
        '#type' => 'html_tag',
        '#tag' => 'main',
        '#attributes' => ['id' => 'wp--skip-link--target', 'class' => ['wp-block-group']],
        'message' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => ['class' => ['has-text-align-center', 'wp-block-paragraph']],
          '#value' => $this->t('This page could not be found. Maybe try a search?'),
        ],
      ],
    ];
  }

}
