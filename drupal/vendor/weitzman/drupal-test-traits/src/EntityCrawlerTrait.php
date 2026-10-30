<?php

declare(strict_types=1);

namespace weitzman\DrupalTestTraits;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Trait for crawling rendered entities.
 */
trait EntityCrawlerTrait
{
  /**
   * Renders an entity and returns a Crawler for testing without drupalGet.
   */
    protected function getRenderedEntityCrawler(EntityInterface $entity, string $view_mode = 'full', array &$build = []): Crawler
    {
        $entityTypeRepository = \Drupal::service('entity_type.repository');
        \assert($entityTypeRepository instanceof EntityTypeRepositoryInterface);
        $entityType = $entityTypeRepository->getEntityTypeFromClass($entity::class);
        $build = \Drupal::entityTypeManager()->getViewBuilder($entityType)->view($entity, $view_mode);
        /** @var \Drupal\Core\Render\Renderer $renderer */
        $renderer = \Drupal::service('renderer');
        $output = $renderer->renderInIsolation($build);
        $crawler = new Crawler((string) $output);
        $this->htmlOutput($crawler->outerHtml());
        return $crawler;
    }

    /**
     * Renders a Block plugin and returns a Crawler for testing without drupalGet.
     */
    protected function getBlockPluginCrawler(string $pluginId, array $configuration = []): Crawler
    {
        $block_manager = \Drupal::service('plugin.manager.block');
        $block = $block_manager->createInstance($pluginId, $configuration);
        $build = $block->build();
        /** @var \Drupal\Core\Render\Renderer $renderer */
        $renderer = \Drupal::service('renderer');
        $output = $renderer->renderInIsolation($build);
        $crawler = new Crawler((string) $output);
        $this->htmlOutput($crawler->outerHtml());
        return $crawler;
    }
}
