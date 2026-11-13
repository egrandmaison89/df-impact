<?php

declare(strict_types=1);

namespace Drupal\df_setup\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sets HTTP 404 on /topics while keeping WP-matching 404 body markup.
 */
final class TopicsIndexNotFoundSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::RESPONSE => ['onResponse', -10]];
  }

  /**
   * Marks /topics responses as 404 to match live WordPress.
   */
  public function onResponse(ResponseEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $request = $event->getRequest();
    if ($request->attributes->get('_route') !== 'df_setup.topics_index') {
      return;
    }
    $event->getResponse()->setStatusCode(404);
  }

}
