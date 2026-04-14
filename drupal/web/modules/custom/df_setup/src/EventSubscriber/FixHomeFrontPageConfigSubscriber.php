<?php

declare(strict_types=1);

namespace Drupal\df_setup\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Normalizes system.site:page.front to /node/NID so isFrontPage() works.
 *
 * Storing the /home alias (or any non-internal path) breaks front page detection
 * because PathMatcher compares the active route's internal path to this setting.
 */
final class FixHomeFrontPageConfigSubscriber implements EventSubscriberInterface {

  private static bool $applying = FALSE;

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AliasManagerInterface $aliasManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ConfigEvents::SAVE => 'onConfigSave'];
  }

  public function onConfigSave(ConfigCrudEvent $event): void {
    if (self::$applying) {
      return;
    }
    if ($event->getConfig()->getName() !== 'system.site') {
      return;
    }
    $front = $event->getConfig()->get('page.front');
    if (!is_string($front) || $front === '') {
      return;
    }
    if (preg_match('#^/node/\d+$#', $front)) {
      return;
    }

    $lookup = str_starts_with($front, '/') ? $front : '/' . $front;
    $resolved = $this->aliasManager->getPathByAlias($lookup);
    if (preg_match('#^/node/\d+$#', $resolved)) {
      $this->applyFrontPath($resolved);
      return;
    }

    // Repo shipped system.site:page.front as /home; normalize using the node.
    if ($lookup === '/home') {
      $node = $this->loadHomeLandingNode();
      if ($node instanceof NodeInterface) {
        $this->applyFrontPath('/node/' . $node->id());
      }
    }
  }

  private function loadHomeLandingNode(): ?NodeInterface {
    $storage = $this->entityTypeManager->getStorage('node');
    $nids = $storage->getQuery()
      ->condition('type', 'page')
      ->condition('title', 'Home')
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();
    if (!$nids) {
      return NULL;
    }
    $node = $storage->load(reset($nids));
    return $node instanceof NodeInterface ? $node : NULL;
  }

  private function applyFrontPath(string $path): void {
    self::$applying = TRUE;
    try {
      $config = $this->configFactory->getEditable('system.site');
      if ($config->get('page.front') !== $path) {
        $config->set('page.front', $path)->save();
      }
    }
    finally {
      self::$applying = FALSE;
    }
  }

}
