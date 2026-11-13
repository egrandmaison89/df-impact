<?php

declare(strict_types=1);

namespace Drupal\df_setup\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Placeholder for former WP legacy redirects (removed for same-path URL parity).
 *
 * Canonical routes: /about, /home, /homepage, /category/{slug} via path aliases
 * and Views (see df_setup_update_10024).
 */
final class WordpressLegacyPathSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    // Before RouterListener (32) so rewritten /category/* paths match Views routes.
    return [KernelEvents::REQUEST => [['onRequest', 20]]];
  }

  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $request = $event->getRequest();
    $path = rtrim($request->getPathInfo(), '/') ?: '/';

    if (preg_match('#^/category/([a-z0-9-]+)/page/(\d+)$#i', $path, $matches)) {
      $slug = $matches[1];
      $page = max(0, (int) $matches[2] - 1);
      $wp_path = "/category/{$slug}/page/" . (int) $matches[2];
      $this->rebuildRequest($request, "/category/{$slug}", ['page' => (string) $page]);
      $request->attributes->set('df_setup_wp_archive_path', $wp_path);
      return;
    }

  }

  private function rebuildRequest(Request $request, string $path, array $query): void {
    $new = $this->duplicateRequest($request, $path, $query);
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

  /**
   * @param array<string, mixed> $query
   */
  private function duplicateRequest(Request $request, string $path, array $query): Request {
    $uri = $path . ($query !== [] ? '?' . http_build_query($query) : '');
    $server = $request->server->all();
    $server['REQUEST_URI'] = $uri;
    $server['QUERY_STRING'] = $query !== [] ? http_build_query($query) : '';
    $base = $request->getSchemeAndHttpHost();
    $new = Request::create(
      $base . $uri,
      $request->getMethod(),
      $query,
      $request->cookies->all(),
      $request->files->all(),
      $server,
      $request->getContent(),
    );
    $new->headers->replace($request->headers->all());
    return $new;
  }

}
