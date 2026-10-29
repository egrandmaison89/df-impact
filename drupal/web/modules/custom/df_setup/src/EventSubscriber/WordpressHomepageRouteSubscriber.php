<?php

declare(strict_types=1);

namespace Drupal\df_setup\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
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
    if ($path === '/' || $path === '/homepage' || $path === '/home') {
      $event->getRequest()->attributes->set('_disable_route_normalizer', TRUE);
    }
    // Render the configured front page (/) for WP /homepage and /home aliases.
    if ($path === '/homepage' || $path === '/home') {
      $this->rebuildRequest($event->getRequest(), '/', $event->getRequest()->query->all());
    }
  }

  /**
   * @param array<string, mixed> $query
   */
  private function rebuildRequest(Request $request, string $path, array $query): void {
    $uri = $path . ($query !== [] ? '?' . http_build_query($query) : '');
    $new = Request::create(
      $uri,
      $request->getMethod(),
      $query,
      $request->cookies->all(),
      $request->files->all(),
      $request->server->all(),
      $request->getContent(),
    );
    $new->headers->replace($request->headers->all());
    $request->initialize(
      $new->query->all(),
      $new->request->all(),
      $new->attributes->all(),
      $new->cookies->all(),
      $new->files->all(),
      $new->server->all(),
      $new->getContent(),
    );
  }

}
