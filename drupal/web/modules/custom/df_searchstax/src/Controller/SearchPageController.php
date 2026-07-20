<?php

declare(strict_types=1);

namespace Drupal\df_searchstax\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;

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
      'public_base_url' => (string) $config->get('public_base_url'),
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
      'filter_options' => $this->buildFilterOptions(),
    ];

    // settings.php wins for secrets and environment-specific overrides.
    $settings = array_replace_recursive($settings, is_array($overrides) ? $overrides : []);
    $settings['required_filter_queries'] = $this->buildRequiredFilterQueries((string) ($settings['public_base_url'] ?? ''));
    // Avoid client 403 when Discovery API key is missing (popular-search requires auth).
    if (trim((string) ($settings['related_searches_api_key'] ?? '')) === '') {
      $settings['popular_searches_url'] = '';
    }
    return $settings;
  }

  /**
   * SearchStax schema currently drops custom source fields, so URL scope is the
   * reliable server-side guard against crawler/listing documents.
   *
   * @return list<string>
   */
  private function buildRequiredFilterQueries(string $publicBase): array {
    $host = parse_url($publicBase, PHP_URL_HOST);
    if (is_string($host) && $host !== '') {
      return ['id:*' . $host . '/stories*'];
    }
    return ['id:*/stories*'];
  }

  /**
   * Filter labels for legacy client-side fallback UI.
   *
   * @return array<string, mixed>
   */
  private function buildFilterOptions(): array {
    $storage = $this->entityTypeManager()->getStorage('taxonomy_term');
    $names = static function (string $vid) use ($storage): array {
      if ($vid === '') {
        return [];
      }
      $terms = $storage->loadTree($vid, 0, NULL, TRUE);
      $out = [];
      foreach ($terms as $term) {
        if ($term instanceof TermInterface && $term->isPublished()) {
          $out[] = $term->getName();
        }
      }
      return $out;
    };

    $issueStorage = $this->entityTypeManager()->getStorage('node');
    $issueNids = $issueStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'issue')
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->execute();
    $issues = [];
    foreach ($issueStorage->loadMultiple($issueNids) as $issue) {
      if ($issue instanceof NodeInterface) {
        $issues[] = $issue->label();
      }
    }

    return [
      'contentKinds' => [
        ['value' => 'Article', 'label' => (string) $this->t('Article')],
      ],
      'issues' => $issues,
      'categories' => $names('content_type'),
      'topics' => [],
      'cancerTypes' => [],
    ];
  }

}
