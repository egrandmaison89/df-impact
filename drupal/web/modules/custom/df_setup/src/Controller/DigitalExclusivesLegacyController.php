<?php

declare(strict_types=1);

namespace Drupal\df_setup\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * WordPress /digital-exclusives parity: live WP returns HTTP 404 at this path.
 */
final class DigitalExclusivesLegacyController extends ControllerBase {

  /**
   * Builds the same not-found main content WordPress serves at /digital-exclusives/.
   */
  public function render(): array {
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
