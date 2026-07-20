<?php

declare(strict_types=1);

namespace Drupal\df_searchstax\Drush\Commands;

use Drupal\Core\Site\Settings;
use Drupal\df_searchstax\Indexing\SearchStaxDocumentBuilder;
use Drupal\node\NodeInterface;
use Drush\Attributes\Argument;
use Drush\Attributes\Bootstrap;
use Drush\Attributes\Command;
use Drush\Attributes\Option;
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
  #[Option(name: 'show-facets', description: 'Print facet buckets for article SearchStax fields')]
  #[Usage(name: 'drush df-searchstax:index-info', description: 'Print Solr numFound via Select API.')]
  #[Usage(name: 'drush df-searchstax:index-info --show-facets', description: 'Also print facet counts (after reindex with facet fields).')]
  public function indexInfo(array $options = ['show-facets' => FALSE]): void {
    ['search_url' => $url, 'search_auth' => $auth] = $this->mergedAuthConfig();
    $url = trim($url);
    $auth = trim($auth);
    if ($url === '' || $auth === '') {
      $this->io()->warning('search_url or search_auth is empty. Use /admin/config/search/df-searchstax or $settings["df_searchstax"] / DF_SEARCHSTAX_* in DDEV.');
      return;
    }

    try {
      $params = [
        'q' => '*',
        'language' => 'en',
        'rows' => 0,
      ];
      if (!empty($options['show-facets'])) {
        $params['facet'] = 'true';
        $params['facet.limit'] = 50;
        $params['facet.mincount'] = 1;
      }
      $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
      if (!empty($options['show-facets'])) {
        foreach ($this->articleFacetFields() as $field) {
          $query .= '&facet.field=' . rawurlencode($field);
        }
      }
      $response = \Drupal::httpClient()->request('GET', $url . '?' . $query, [
        'headers' => [
          'Authorization' => 'Token ' . $auth,
          'Accept' => 'application/json',
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

    if (!empty($options['show-facets'])) {
      $facetFields = $data['facet_counts']['facet_fields'] ?? NULL;
      if (!is_array($facetFields)) {
        $this->io()->note('No facet_counts in response. Configure article fields in SearchStax Studio, then re-run drush df-searchstax:push-content.');
      }
      else {
        foreach ($this->articleFacetFields() as $fname) {
          $buckets = $facetFields[$fname] ?? NULL;
          if (!is_array($buckets) || $buckets === []) {
            $this->io()->writeln(dt('@f: (no values or field not in schema yet)', ['@f' => $fname]));
            continue;
          }
          $this->io()->writeln($fname . ':');
          for ($i = 0; $i + 1 < count($buckets); $i += 2) {
            $this->io()->writeln('  ' . (string) $buckets[$i] . ' — ' . (string) $buckets[$i + 1]);
          }
        }
      }
    }

    if ($num === 0) {
      $this->io()->note('Index is empty. Use drush df-searchstax:push-content after configuring update_url and a read-write index token, or run a Studio crawler.');
    }
  }

  /**
   * Pushes published Impact nodes to SearchStax (Ingest API).
   */
  #[Command(name: 'df-searchstax:push-content')]
  #[Bootstrap(level: DrupalBootLevels::FULL)]
  #[Option(name: 'bundle', description: 'Optional bundle. Article search currently supports article only.')]
  #[Option(name: 'limit', description: 'Max nodes to push.')]
  #[Usage(name: 'drush df-searchstax:push-content', description: 'Index all published nodes')]
  #[Usage(name: 'drush df-searchstax:push-content --bundle=article --limit=50', description: 'Index up to 50 articles')]
  public function pushContent(array $options = ['bundle' => NULL, 'limit' => NULL]): void {
    $bundle = $options['bundle'] !== NULL && $options['bundle'] !== '' ? (string) $options['bundle'] : NULL;
    $limit = isset($options['limit']) && $options['limit'] !== NULL && $options['limit'] !== '' ? (int) $options['limit'] : NULL;

    if ($bundle !== NULL && !in_array($bundle, SearchStaxDocumentBuilder::INDEXED_BUNDLES, TRUE)) {
      $this->io()->error(dt('Invalid bundle @b', ['@b' => $bundle]));
      return;
    }

    /** @var \Drupal\df_searchstax\Indexing\SearchStaxDocumentBuilder $builder */
    $builder = \Drupal::service('df_searchstax.document_builder');
    /** @var \Drupal\df_searchstax\Indexing\SearchStaxUpdateClient $client */
    $client = \Drupal::service('df_searchstax.update_client');
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $et */
    $et = \Drupal::entityTypeManager();

    $base = $this->publicBaseUrlForCli();

    $query = $et->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', NodeInterface::PUBLISHED)
      ->sort('nid');
    if ($bundle !== NULL) {
      $query->condition('type', $bundle);
    }
    else {
      $query->condition('type', SearchStaxDocumentBuilder::INDEXED_BUNDLES, 'IN');
    }
    if ($limit !== NULL && $limit > 0) {
      $query->range(0, $limit);
    }
    $nids = $query->execute();
    if ($nids === []) {
      $this->io()->writeln('No published nodes matched.');
      return;
    }

    $batch = [];
    $batchSize = 20;
    $ok = 0;
    $fail = 0;
    foreach ($nids as $nid) {
      $node = $et->getStorage('node')->load($nid);
      if (!$node instanceof \Drupal\node\NodeInterface) {
        continue;
      }
      $doc = $builder->build($node, $base);
      if ($doc === NULL) {
        continue;
      }
      $batch[] = $doc;
      if (count($batch) >= $batchSize) {
        if ($client->indexDocuments($batch)) {
          $ok += count($batch);
        }
        else {
          $fail += count($batch);
        }
        $batch = [];
      }
    }
    if ($batch !== []) {
      if ($client->indexDocuments($batch)) {
        $ok += count($batch);
      }
      else {
        $fail += count($batch);
      }
    }
    $this->io()->success(dt('Pushed @ok documents (@fail batch failures).', [
      '@ok' => (string) $ok,
      '@fail' => (string) $fail,
    ]));
  }

  /**
   * Deletes legacy Solr documents whose id used the impact-{bundle}-{nid}-{lang} scheme.
   *
   * Current ingests use the canonical page URL as the Solr id (fields like url_s may be
   * dropped by SearchStax). This only removes the old prefix-keyed documents.
   */
  #[Command(name: 'df-searchstax:delete-drupal')]
  #[Bootstrap(level: DrupalBootLevels::FULL)]
  #[Usage(name: 'drush df-searchstax:delete-drupal', description: 'Remove Solr docs with id prefix impact- (legacy). Use -y to skip confirm.')]
  public function deleteDrupalDocuments(): void {
    if (!$this->io()->confirm(dt('Remove all Solr documents whose id starts with "impact-"?'))) {
      $this->io()->writeln('Aborted.');
      return;
    }
    /** @var \Drupal\df_searchstax\Indexing\SearchStaxUpdateClient $client */
    $client = \Drupal::service('df_searchstax.update_client');
    if ($client->deleteByQuery('id:impact*')) {
      $this->io()->success('Delete-by-query submitted. Re-run drush df-searchstax:push-content to load URL-keyed docs. Check drush df-searchstax:index-info');
    }
    else {
      $this->io()->error('Delete failed (check logs and Ingest token).');
    }
  }

  /**
   * Deletes documents matching a SearchStax/Solr query.
   */
  #[Command(name: 'df-searchstax:delete-by-query')]
  #[Argument(name: 'query', description: 'Solr delete query, for example id:*category* or search_scope_s:articles.')]
  #[Bootstrap(level: DrupalBootLevels::FULL)]
  #[Usage(name: 'drush df-searchstax:delete-by-query "id:*category*"', description: 'Remove matching category/listing documents after confirmation.')]
  public function deleteByQueryCommand(string $query): void {
    $query = trim($query);
    if ($query === '') {
      $this->io()->error('No query provided.');
      return;
    }
    if (!$this->io()->confirm(dt('Delete all SearchStax documents matching this query? @query', ['@query' => $query]))) {
      $this->io()->writeln('Aborted.');
      return;
    }
    /** @var \Drupal\df_searchstax\Indexing\SearchStaxUpdateClient $client */
    $client = \Drupal::service('df_searchstax.update_client');
    if ($client->deleteByQuery($query)) {
      $this->io()->success(dt('Delete-by-query submitted: @query', ['@query' => $query]));
    }
    else {
      $this->io()->error('Delete-by-query failed (check logs and Ingest token).');
    }
  }

  /**
   * Deletes common crawler/listing documents that should not appear in article search.
   */
  #[Command(name: 'df-searchstax:delete-low-value')]
  #[Bootstrap(level: DrupalBootLevels::FULL)]
  #[Usage(name: 'drush df-searchstax:delete-low-value', description: 'Remove known non-article crawler URL patterns after confirmation.')]
  public function deleteLowValueDocuments(): void {
    $queries = [
      'id:*category*',
      'id:*?page=*',
      'id:*/about-impact',
    ];
    $this->io()->listing($queries);
    if (!$this->io()->confirm('Submit these low-value SearchStax delete queries?')) {
      $this->io()->writeln('Aborted.');
      return;
    }

    /** @var \Drupal\df_searchstax\Indexing\SearchStaxUpdateClient $client */
    $client = \Drupal::service('df_searchstax.update_client');
    $ok = 0;
    $fail = 0;
    foreach ($queries as $query) {
      if ($client->deleteByQuery($query)) {
        $ok++;
      }
      else {
        $fail++;
        $this->io()->warning(dt('Delete failed for @query', ['@query' => $query]));
      }
    }
    $this->io()->writeln(dt('Submitted @ok low-value delete queries; @fail failed.', [
      '@ok' => (string) $ok,
      '@fail' => (string) $fail,
    ]));
  }

  /**
   * Deletes specific Solr documents by id (Ingest API).
   */
  #[Command(name: 'df-searchstax:delete-by-ids')]
  #[Argument(name: 'ids', description: 'Comma-separated Solr document ids (e.g. impact-test-drupal-1-en).')]
  #[Bootstrap(level: DrupalBootLevels::FULL)]
  #[Usage(name: 'drush df-searchstax:delete-by-ids impact-lang-test-1,impact-test-drupal-1-en', description: 'Remove manual test documents (confirm or use -y)')]
  public function deleteByIds(string $ids): void {
    if (!$this->io()->confirm(dt('Delete these Solr documents from SearchStax? @ids', ['@ids' => $ids]))) {
      $this->io()->writeln('Aborted.');
      return;
    }
    $partsRaw = preg_split('/\s*,\s*/', trim($ids)) ?: [];
    $parts = array_values(array_filter(array_map('trim', $partsRaw), static fn(string $s): bool => $s !== ''));
    if ($parts === []) {
      $this->io()->error('No ids provided.');
      return;
    }
    /** @var \Drupal\df_searchstax\Indexing\SearchStaxUpdateClient $client */
    $client = \Drupal::service('df_searchstax.update_client');
    $ok = 0;
    $fail = 0;
    foreach ($parts as $id) {
      if ($client->deleteById($id)) {
        $ok++;
      }
      else {
        $fail++;
      }
    }
    $this->io()->writeln(dt('Deleted @ok id(s); @fail failed.', [
      '@ok' => (string) $ok,
      '@fail' => (string) $fail,
    ]));
  }

  private function publicBaseUrlForCli(): string {
    $config = \Drupal::config('df_searchstax.settings');
    $url = trim((string) $config->get('public_base_url'));
    $overrides = Settings::get('df_searchstax', []);
    if (is_array($overrides) && !empty($overrides['public_base_url'])) {
      $url = trim((string) $overrides['public_base_url']);
    }
    if ($url !== '') {
      return rtrim($url, '/');
    }
    try {
      return rtrim((string) \Drupal::request()->getSchemeAndHttpHost(), '/');
    }
    catch (\Throwable) {
      return '';
    }
  }

  /**
   * @return list<string>
   */
  private function articleFacetFields(): array {
    return [
      'index_source_s',
      'search_scope_s',
      'content_type_s',
      'issue_title_s',
      'article_category_s',
    ];
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
