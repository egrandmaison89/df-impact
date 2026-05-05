<?php

declare(strict_types=1);

namespace Drupal\df_setup\EventSubscriber;

use Drupal\Core\Config\ConfigImporterEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\df_setup\DigitalExclusiveChannels;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Creates the Digital Exclusives term after config import when the vocabulary appears.
 */
final class EnsureDigitalExclusiveTermSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly DigitalExclusiveChannels $digitalExclusiveChannels,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ConfigEvents::IMPORT => 'onConfigImport'];
  }

  public function onConfigImport(ConfigImporterEvent $event): void {
    $this->digitalExclusiveChannels->ensureTermId();
  }

}
