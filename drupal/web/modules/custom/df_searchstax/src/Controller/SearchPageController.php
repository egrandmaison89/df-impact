<?php

declare(strict_types=1);

namespace Drupal\df_searchstax\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;

/**
 * Returns the SearchStax search page.
 */
final class SearchPageController extends ControllerBase {

  /**
   * Builds the search page.
   */
  public function content(): array {
    return [
      '#theme' => 'df_searchstax_page',
      '#cache' => [
        'max-age' => 0,
      ],
      '#attached' => [
        'library' => [
          'df_searchstax/search_page',
        ],
        'drupalSettings' => [
          'dfSearchstax' => $this->buildFrontendSettings(),
        ],
      ],
    ];
  }

  /**
   * Prepares frontend configuration, merging config + settings.php overrides.
   *
   * @return array<string, mixed>
   *   SearchStax settings for drupalSettings.
   */
  private function buildFrontendSettings(): array {
    $config = $this->config('df_searchstax.settings');
    $overrides = Settings::get('df_searchstax', []);

    $settings = [
      'language' => (string) $config->get('language'),
      'search_url' => (string) $config->get('search_url'),
      'suggester_url' => (string) $config->get('suggester_url'),
      'search_auth' => (string) $config->get('search_auth'),
      'auth_type' => (string) $config->get('auth_type'),
      'analytics_base_url' => (string) $config->get('analytics_base_url'),
      'track_api_key' => (string) $config->get('track_api_key'),
      'analytics_src' => (string) $config->get('analytics_src'),
      'question_url' => (string) $config->get('question_url'),
      'related_searches_url' => (string) $config->get('related_searches_url'),
      'related_searches_api_key' => (string) $config->get('related_searches_api_key'),
      'popular_searches_url' => (string) $config->get('popular_searches_url'),
      'geocoding_url' => (string) $config->get('geocoding_url'),
      'country_code' => (string) $config->get('country_code'),
      'app_id' => (string) $config->get('app_id'),
      'results_render_method' => (string) $config->get('results_render_method'),
      'facets_items_per_page_desktop' => (int) $config->get('facets_items_per_page_desktop'),
      'facets_items_per_page_mobile' => (int) $config->get('facets_items_per_page_mobile'),
      'faceting_type' => (string) $config->get('faceting_type'),
      'features' => (array) $config->get('features'),
    ];

    // settings.php wins for secrets and environment-specific overrides.
    return array_replace_recursive($settings, is_array($overrides) ? $overrides : []);
  }

}
