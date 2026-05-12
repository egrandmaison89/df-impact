<?php

declare(strict_types=1);

namespace Drupal\df_searchstax\Indexing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Site\Settings;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * POSTs JSON to SearchStax Site Search Ingest (Solr update handlers).
 */
final class SearchStaxUpdateClient {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ClientInterface $httpClient,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Indexes or replaces documents (array of associative arrays).
   *
   * @param array<int, array<string, mixed>> $documents
   */
  public function indexDocuments(array $documents): bool {
    if ($documents === []) {
      return TRUE;
    }
    [$url, $token] = $this->indexEndpoint();
    if ($url === '' || $token === '') {
      $this->logger->warning('SearchStax index push skipped: update_url or index_auth missing.');
      return FALSE;
    }

    $endpoint = $this->withCommit($this->jsonDocsIngestUrl($url));

    try {
      $response = $this->httpClient->request('POST', $endpoint, [
        'headers' => [
          'Authorization' => 'Token ' . $token,
          'Content-Type' => 'application/json',
          'Accept' => 'application/json',
        ],
        'body' => json_encode($documents, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        'timeout' => 120,
      ]);
    }
    catch (GuzzleException $e) {
      $this->logger->error('SearchStax update failed: @msg', ['@msg' => $e->getMessage()]);
      return FALSE;
    }
    catch (\JsonException $e) {
      $this->logger->error('SearchStax JSON encode failed: @msg', ['@msg' => $e->getMessage()]);
      return FALSE;
    }

    $code = $response->getStatusCode();
    $body = (string) $response->getBody();
    if ($code < 200 || $code >= 300) {
      $this->logger->error('SearchStax update HTTP @code: @body', [
        '@code' => (string) $code,
        '@body' => $body,
      ]);
      return FALSE;
    }

    $decoded = json_decode($body, TRUE);
    if (is_array($decoded) && array_key_exists('responseHeader', $decoded)) {
      $solrStatus = $decoded['responseHeader']['status'] ?? NULL;
      if ($solrStatus !== NULL && (int) $solrStatus !== 0) {
        $this->logger->error('SearchStax Solr responseHeader.status=@s body=@body', [
          '@s' => (string) $solrStatus,
          '@body' => $body,
        ]);
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Deletes documents by Solr query (e.g. index_source_s:drupal).
   */
  public function deleteByQuery(string $query): bool {
    [$url, $token] = $this->indexEndpoint();
    if ($url === '' || $token === '') {
      return FALSE;
    }
    $endpoint = $this->withCommit($url);
    $payload = ['delete' => ['query' => $query]];

    try {
      $response = $this->httpClient->request('POST', $endpoint, [
        'headers' => [
          'Authorization' => 'Token ' . $token,
          'Content-Type' => 'application/json',
        ],
        'body' => json_encode($payload, JSON_THROW_ON_ERROR),
        'timeout' => 120,
      ]);
    }
    catch (\Throwable $e) {
      $this->logger->error('SearchStax delete query failed: @msg', ['@msg' => $e->getMessage()]);
      return FALSE;
    }

    $code = $response->getStatusCode();
    return $code >= 200 && $code < 300;
  }

  /**
   * Deletes a single document by id.
   */
  public function deleteById(string $id): bool {
    [$url, $token] = $this->indexEndpoint();
    if ($url === '' || $token === '') {
      return FALSE;
    }
    $endpoint = $this->withCommit($url);
    $payload = ['delete' => ['id' => $id]];

    try {
      $response = $this->httpClient->request('POST', $endpoint, [
        'headers' => [
          'Authorization' => 'Token ' . $token,
          'Content-Type' => 'application/json',
        ],
        'body' => json_encode($payload, JSON_THROW_ON_ERROR),
        'timeout' => 60,
      ]);
    }
    catch (\Throwable $e) {
      $this->logger->error('SearchStax delete id failed: @msg', ['@msg' => $e->getMessage()]);
      return FALSE;
    }

    $code = $response->getStatusCode();
    return $code >= 200 && $code < 300;
  }

  /**
   * @return array{0: string, 1: string}
   */
  private function indexEndpoint(): array {
    $config = $this->configFactory->get('df_searchstax.settings');
    $url = (string) $config->get('update_url');
    $token = (string) $config->get('index_auth');
    $overrides = Settings::get('df_searchstax', []);
    if (is_array($overrides)) {
      if (!empty($overrides['update_url'])) {
        $url = (string) $overrides['update_url'];
      }
      if (!empty($overrides['index_auth'])) {
        $token = (string) $overrides['index_auth'];
      }
      elseif (!empty($overrides['search_auth']) && $token === '') {
        // Dev convenience; read-only tokens will fail with HTTP error.
        $token = (string) $overrides['search_auth'];
      }
    }
    return [trim($url), trim($token)];
  }

  /**
   * Appends commit=true, preserving an existing query string on the URL.
   */
  private function withCommit(string $url): string {
    return $url . (str_contains($url, '?') ? '&' : '?') . 'commit=true';
  }

  /**
   * Maps .../update to .../update/json/docs for Solr JSON document arrays.
   */
  private function jsonDocsIngestUrl(string $updateUrl): string {
    $updateUrl = trim($updateUrl);
    $qPos = strpos($updateUrl, '?');
    $pathUrl = $qPos === FALSE ? $updateUrl : substr($updateUrl, 0, $qPos);
    $query = $qPos === FALSE ? '' : substr($updateUrl, $qPos);
    $pathUrl = rtrim($pathUrl, '/');
    if (str_ends_with($pathUrl, '/update/json/docs')) {
      return $pathUrl . $query;
    }
    if (str_ends_with($pathUrl, '/update')) {
      return $pathUrl . '/json/docs' . $query;
    }
    return $pathUrl . '/update/json/docs' . $query;
  }

}
