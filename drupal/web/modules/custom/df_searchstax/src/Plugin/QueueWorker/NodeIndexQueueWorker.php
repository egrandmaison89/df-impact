<?php

declare(strict_types=1);

namespace Drupal\df_searchstax\Plugin\QueueWorker;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\df_searchstax\Indexing\SearchStaxDocumentBuilder;
use Drupal\df_searchstax\Indexing\SearchStaxUpdateClient;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes node index/delete jobs for SearchStax.
 *
 * @QueueWorker(
 *   id = "df_searchstax_node_index",
 *   title = @Translation("SearchStax: index nodes"),
 *   cron = {"time" = 45}
 * )
 */
final class NodeIndexQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly SearchStaxDocumentBuilder $documentBuilder,
    private readonly SearchStaxUpdateClient $updateClient,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('df_searchstax.document_builder'),
      $container->get('df_searchstax.update_client'),
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    if (!is_array($data)) {
      return;
    }
    $nid = (int) ($data['nid'] ?? 0);
    $langcode = (string) ($data['langcode'] ?? 'en');
    $bundle = (string) ($data['bundle'] ?? '');
    $op = (string) ($data['op'] ?? 'index');
    if ($nid <= 0 || $bundle === '') {
      return;
    }

    $base = $this->publicBaseUrl();
    $searchstaxId = (string) ($data['searchstax_id'] ?? '');
    $legacyId = sprintf('impact-%s-%s-%s', $bundle, $nid, $langcode);

    if ($op === 'delete') {
      if ($searchstaxId !== '') {
        $this->updateClient->deleteById($searchstaxId);
      }
      else {
        $this->updateClient->deleteById($legacyId);
      }
      return;
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $node = $storage->load($nid);
    if (!$node instanceof NodeInterface) {
      $this->updateClient->deleteById($searchstaxId !== '' ? $searchstaxId : $legacyId);
      return;
    }
    if (!$node->hasTranslation($langcode)) {
      $this->updateClient->deleteById($searchstaxId !== '' ? $searchstaxId : $legacyId);
      return;
    }
    $node = $node->getTranslation($langcode);
    if (!$node->isPublished()) {
      $this->updateClient->deleteById($searchstaxId !== '' ? $searchstaxId : $legacyId);
      return;
    }

    $doc = $this->documentBuilder->build($node, $base);
    if ($doc === NULL) {
      $this->updateClient->deleteById($searchstaxId !== '' ? $searchstaxId : $legacyId);
      return;
    }
    if ($searchstaxId !== '' && $searchstaxId !== (string) ($doc['id'] ?? '')) {
      $this->updateClient->deleteById($searchstaxId);
    }
    $this->updateClient->indexDocuments([$doc]);
  }

  private function publicBaseUrl(): string {
    $url = (string) $this->configFactory->get('df_searchstax.settings')->get('public_base_url');
    $url = trim($url);
    if ($url !== '') {
      return rtrim($url, '/');
    }
    try {
      $request = \Drupal::request();
      return rtrim($request->getSchemeAndHttpHost(), '/');
    }
    catch (\Throwable) {
      return '';
    }
  }

}
