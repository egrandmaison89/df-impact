<?php

declare(strict_types=1);

namespace Drupal\df_secondary\EventSubscriber;

use Drupal\df_secondary\WordPressIdMap;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Legacy WordPress URLs: ?p=, uploads, search ?s=, feeds, sitemap aliases, author.
 */
final class WordpressSecondaryRequestSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly WordPressIdMap $wpIdMap,
  ) {}

  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => [['onRequest', 35]],
    ];
  }

  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $request = $event->getRequest();
    $path = rtrim($request->getPathInfo(), '/') ?: '/';

    if ($request->query->has('p')) {
      $wp_id = (int) $request->query->get('p');
      if ($wp_id > 0) {
        $target = $this->wpIdMap->pathForPostId($wp_id);
        if ($target !== NULL) {
          $event->setResponse(new RedirectResponse($target, 301));
          return;
        }
      }
    }

    if (str_starts_with($path, '/wp-content/uploads/')) {
      $suffix = substr($path, strlen('/wp-content/uploads/'));
      $this->rebuildRequest($request, '/sites/default/files/wp-images/' . $suffix, $request->query->all());
      return;
    }

    if ($path === '/feed' || $path === '/feed/') {
      $this->rebuildRequest($request, '/rss.xml', []);
      return;
    }

    if (preg_match('#^/(.+)/feed/?$#i', $path, $matches) && !str_starts_with($path, '/category/')) {
      $alias_path = '/' . $matches[1];
      $system_path = \Drupal::service('path_alias.manager')->getPathByAlias($alias_path);
      if (preg_match('#^/node/(\d+)$#', $system_path, $node_match)) {
        $this->rebuildRequest($request, '/node/' . $node_match[1] . '/feed', []);
      }
      return;
    }

    if (in_array($path, ['/sitemap_index.xml', '/wp-sitemap.xml'], TRUE)) {
      $this->rebuildRequest($request, '/sitemaps/default/sitemap.xml', []);
      return;
    }

    if (preg_match('#^/author/([a-z0-9-]+)(?:/page/(\d+))?/?$#i', $path, $matches)) {
      $page = isset($matches[2]) ? max(0, (int) $matches[2] - 1) : 0;
      $query = $page > 0 ? ['page' => (string) $page] : [];
      $this->rebuildRequest($request, '/category/' . $matches[1], $query);
      return;
    }

    $search_term = $request->query->get('s');
    if ($search_term !== NULL && $search_term !== '' && ($path === '/' || $path === '/homepage')) {
      $this->rebuildRequest($request, '/search', ['s' => $search_term]);
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
