<?php

declare(strict_types=1);

namespace Drupal\df_setup\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Maps WP category pagination URLs before routing (/category/{slug}/page/N).
 */
final class TopicCategoryArchivePaginateSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [KernelEvents::REQUEST => [['onRequest', 45]]];
  }

  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $request = $event->getRequest();
    $path = $request->getPathInfo();
    if (!preg_match('#^/category/([^/]+)/page/(\d+)/?$#', $path, $matches)) {
      return;
    }
    $slug = $matches[1];
    $wp_page = max(1, (int) $matches[2]);
    $drupal_page = $wp_page - 1;
    $query = array_merge($request->query->all(), ['page' => (string) $drupal_page]);
    $request->query->replace($query);
    $uri = $request->getBaseUrl() . '/category/' . rawurlencode($slug);
    if ($query !== []) {
      $uri .= '?' . http_build_query($query);
    }
    $request->server->set('REQUEST_URI', $uri);
    $request->server->set('QUERY_STRING', http_build_query($query));
    $ref = new \ReflectionClass(Request::class);
    if ($ref->hasProperty('pathInfo')) {
      $prop = $ref->getProperty('pathInfo');
      $prop->setAccessible(TRUE);
      $prop->setValue($request, NULL);
    }
    if ($ref->hasProperty('requestUri')) {
      $prop = $ref->getProperty('requestUri');
      $prop->setAccessible(TRUE);
      $prop->setValue($request, NULL);
    }
  }

}
