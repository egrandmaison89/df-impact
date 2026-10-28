<?php

declare(strict_types=1);

namespace Drupal\df_setup\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Serves /homepage and / with HTTP 200 for WordPress URL parity.
 *
 * Drupal's redirect module normalizes front-page aliases to the site root (/).
 * Live WordPress serves the home landing at both / and /homepage/.
 */
final class WordpressHomepageRouteSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => ['onRequest', 35],
    ];
  }

  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $path = rtrim($event->getRequest()->getPathInfo(), '/') ?: '/';
    if ($path === '/homepage' || $path === '/') {
      $event->getRequest()->attributes->set('_disable_route_normalizer', TRUE);
    }
  }

}
