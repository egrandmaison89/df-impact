<?php

declare(strict_types=1);

namespace Drupal\df_editor\EventSubscriber;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\df_editor\IssuePublisher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Tags HTML responses that embed issue-driven navigation and homepage hero.
 */
final class IssueNavPageCacheSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Must run before DynamicPageCacheSubscriber (priority 7) stores metadata.
    return [KernelEvents::RESPONSE => ['onResponse', 10]];
  }

  public function onResponse(ResponseEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $response = $event->getResponse();
    if (!$response instanceof CacheableResponseInterface) {
      return;
    }
    $tags = [
      'node_list:issue',
      'node_list:article',
      IssuePublisher::CACHE_TAG_ISSUE_NAV,
      'config:df_editor.settings',
    ];
    if (function_exists('df_setup_get_latest_issue_nid')) {
      $latest = df_setup_get_latest_issue_nid();
      if ($latest) {
        $tags[] = 'node:' . $latest;
      }
    }
    $metadata = $response->getCacheableMetadata();
    $metadata->addCacheTags($tags);
    $metadata->addCacheContexts(['languages:language_interface']);
    $metadata->setCacheMaxAge(Cache::PERMANENT);
  }

}
