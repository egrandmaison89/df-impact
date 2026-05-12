<?php

declare(strict_types=1);

namespace Drupal\df_searchstax\Drush\Commands;

use Drupal\Core\Site\Settings;
use Drush\Attributes\Bootstrap;
use Drush\Attributes\Command;
use Drush\Attributes\Usage;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Drush helpers for SearchStax.
 */
final class DfSearchstaxCommands extends DrushCommands {

  /**
   * Shows document count from the SearchStax Select API (Solr).
   */
  #[Command(name: 'df-searchstax:index-info')]
  #[Bootstrap(level: DrupalBootLevels::FULL)]
  #[Usage(name: 'drush df-searchstax:index-info', description: 'Print Solr numFound via Select API.')]
  public function indexInfo(): void {
    ['search_url' => $url, 'search_auth' => $auth] = $this->mergedAuthConfig();
    $url = trim($url);
    $auth = trim($auth);
    if ($url === '' || $auth === '') {
      $this->io()->warning('search_url or search_auth is empty. Use /admin/config/search/df-searchstax or $settings["df_searchstax"] / DF_SEARCHSTAX_* in DDEV.');
      return;
    }

    try {
      $response = \Drupal::httpClient()->request('GET', $url, [
        'headers' => [
          'Authorization' => 'Token ' . $auth,
          'Accept' => 'application/json',
        ],
        'query' => [
          'q' => '*',
          'language' => 'en',
          'rows' => 0,
        ],
        'timeout' => 20,
      ]);
    }
    catch (GuzzleException $e) {
      $this->io()->error('Select API request failed: ' . $e->getMessage());
      return;
    }

    $code = $response->getStatusCode();
    if ($code !== 200) {
      $this->io()->error(dt('Select API HTTP @code', ['@code' => (string) $code]));
      return;
    }

    $data = json_decode((string) $response->getBody(), TRUE);
    if (!is_array($data)) {
      $this->io()->error('Select API returned non-JSON or invalid payload.');
      return;
    }

    $num = $data['response']['numFound'] ?? NULL;
    $this->io()->writeln(dt('SearchStax index documents (numFound): @n', [
      '@n' => $num === NULL ? '(missing in response)' : (string) $num,
    ]));

    if ($num === 0) {
      $this->io()->note('Index is empty. Run the Site Search crawler in SearchStax Studio against a reachable URL and sitemap, then re-run this command.');
    }
  }

  /**
   * @return array{search_url: string, search_auth: string}
   */
  private function mergedAuthConfig(): array {
    $config = \Drupal::config('df_searchstax.settings');
    $base = [
      'search_url' => (string) $config->get('search_url'),
      'search_auth' => (string) $config->get('search_auth'),
    ];
    $overrides = Settings::get('df_searchstax', []);
    $merged = array_replace_recursive($base, is_array($overrides) ? $overrides : []);

    return [
      'search_url' => (string) ($merged['search_url'] ?? ''),
      'search_auth' => (string) ($merged['search_auth'] ?? ''),
    ];
  }

}
