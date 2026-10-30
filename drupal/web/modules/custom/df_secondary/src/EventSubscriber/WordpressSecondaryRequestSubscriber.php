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
      KernelEvents::REQUEST => [
        ['onRequestPreserveTrailingSlash', 29],
        ['onRequestSearchQuery', 33],
        ['onRequest', 35],
      ],
    ];
  }

  /**
   * WP ?s= on home paths → /search (before RouterListener; see onRequest for /search → view).
   */
  public function onRequestSearchQuery(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $request = $event->getRequest();
    $search_term = $request->query->get('s');
    if ($search_term === NULL || $search_term === '') {
      return;
    }
    $path = rtrim($request->getPathInfo(), '/') ?: '/';
    $query = ['s' => (string) $search_term];
    if (preg_match('#^/page/(\d+)$#', $path, $matches)) {
      // WP search pagination: /page/N/?s=term.
      $path = '/';
      if ((int) $matches[1] > 1) {
        $query['page'] = (string) ((int) $matches[1] - 1);
      }
    }
    elseif (ctype_digit((string) $request->query->get('page', '')) && (int) $request->query->get('page') > 0) {
      $query['page'] = (string) (int) $request->query->get('page');
    }
    if (!in_array($path, ['/', '/homepage', '/home'], TRUE)) {
      return;
    }
    $target = '/search?' . http_build_query($query);
    $event->setResponse(new RedirectResponse($request->getBaseUrl() . $target, 302));
  }


  /**
   * Keep WordPress-style trailing slashes (before redirect module normalizer).
   */
  public function onRequestPreserveTrailingSlash(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $path_info = $event->getRequest()->getPathInfo();
    if ($path_info === '/' || !str_ends_with($path_info, '/')) {
      return;
    }
    $path = rtrim($path_info, '/') ?: '/';
    if ($this->usesWordpressTrailingSlash($path)) {
      $event->getRequest()->attributes->set('_disable_route_normalizer', TRUE);
    }
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

    if ($path === '/feed' || $path === '/feed/' || $path === '/rss.xml') {
      $this->rebuildRequest($request, '/df-secondary/site-rss', []);
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

    if ($this->shouldRedirectToTrailingSlash($request, $path) && $this->usesWordpressTrailingSlash($path)) {
      // Internal rewrite preserves manifest same-path 200; canonical URLs use trailing slashes in metatags/RSS.
      $this->rebuildRequest($request, $path . '/', $request->query->all());
      return;
    }

    // Core Drupal search module owns `/search`; Search API view lives at `/search-legacy`.
    if ($path === '/search') {
      $query = $request->query->all();
      $this->rebuildRequest($request, '/search-legacy', $query);
    }
  }

  /**
   * @param array<string, mixed> $query
   */
  private function shouldRedirectToTrailingSlash(Request $request, string $path): bool {
    if ($request->getMethod() !== 'GET') {
      return FALSE;
    }
    if ($path === '/' || str_ends_with($request->getPathInfo(), '/')) {
      return FALSE;
    }
    if (str_contains(basename($path), '.')) {
      return FALSE;
    }
    return TRUE;
  }

  private function usesWordpressTrailingSlash(string $path): bool {
    static $skip_exact = [
      '/search' => TRUE,
      '/feed' => TRUE,
      '/rss.xml' => TRUE,
      '/robots.txt' => TRUE,
      '/sitemap_index.xml' => TRUE,
      '/wp-sitemap.xml' => TRUE,
      '/homepage' => TRUE,
      '/home' => TRUE,
      '/issues' => TRUE,
      '/in-brief' => TRUE,
      '/stories' => TRUE,
      '/about' => TRUE,
    ];
    if (isset($skip_exact[$path])) {
      return FALSE;
    }
    if (preg_match('#^/(?:admin|user|node|sites|core|wp-admin|wp-content|sitemaps)(?:/|$)#i', $path)) {
      return FALSE;
    }
    if (preg_match('#/feed/?$#i', $path)) {
      return FALSE;
    }
    return (bool) preg_match('#^/(?:category/[a-z0-9-]+|\d{4}/\d{2}/|issue/)#i', $path);
  }

  private function rebuildRequest(Request $request, string $path, array $query): void {
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
