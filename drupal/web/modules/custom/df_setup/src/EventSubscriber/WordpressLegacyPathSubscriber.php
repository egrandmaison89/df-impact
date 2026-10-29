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
    return [KernelEvents::REQUEST => [['onRequest', 40]]];
  }

  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $request = $event->getRequest();
    $path = rtrim($request->getPathInfo(), '/') ?: '/';

    if ($path === '/digital-exclusives') {
      $this->rebuildRequest($request, '/category/digital-exclusives', []);
      return;
    }

    if ($path === '/stories') {
      $this->rebuildRequest($request, '/issues', []);
      return;
    }
    if ($path === '/digital-exclusives') {
      $this->rebuildRequest($request, '/category/digital-exclusives', []);
      return;
    }

    if (preg_match('#^/category/([a-z0-9-]+)/page/(\d+)$#i', $path, $matches)) {
      $slug = $matches[1];
      $page = max(0, (int) $matches[2] - 1);
      $this->rebuildRequest($request, "/category/{$slug}", ['page' => (string) $page]);
      return;
    }

    if (preg_match('#^/tag/([a-z0-9-]+)(?:/page/(\d+))?$#i', $path, $matches)) {
      $slug = $matches[1];
      $page = isset($matches[2]) ? max(0, (int) $matches[2] - 1) : 0;
      $issue_path = $this->issuePathForTag($slug);
      $target = $issue_path ?? '/category/' . $slug;
      $this->rebuildRequest(
        $request,
        $target,
        $page > 0 ? ['page' => (string) $page] : [],
      );
    }
  }

  /**
   * WordPress post tags that map to Drupal issue landing paths (issues.json).
   */
  private function issuePathForTag(string $tag_slug): ?string {
    static $map = NULL;
    if ($map === NULL) {
      $map = [];
      $file = \Drupal::root() . '/modules/custom/df_migrate/data/issues.json';
      if (is_readable($file)) {
        $rows = json_decode((string) file_get_contents($file), TRUE);
        if (is_array($rows)) {
          foreach ($rows as $row) {
            if (!empty($row['slug']) && !empty($row['path_alias'])) {
              $map[(string) $row['slug']] = (string) $row['path_alias'];
            }
          }
        }
      }
    }
    return $map[$tag_slug] ?? NULL;
  }

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
