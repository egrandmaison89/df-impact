<?php

declare(strict_types=1);

namespace Drupal\df_setup\EventSubscriber;

use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sets HTTP 404 on legacy issue URLs that WordPress no longer serves.
 */
final class LegacyIssueNotFoundSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::RESPONSE => ['onResponse', -10]];
  }

  /**
   * Marks legacy issue node responses as 404 to match live WordPress.
   */
  public function onResponse(ResponseEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $request = $event->getRequest();
    if ($request->attributes->get('_route') !== 'entity.node.canonical') {
      return;
    }
    $node = $request->attributes->get('node');
    if (!$node instanceof NodeInterface || $node->bundle() !== 'issue') {
      return;
    }
    $alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $node->id());
    if (df_setup_legacy_issue_tag_slug_for_request_path($alias) === '') {
      return;
    }
    $event->getResponse()->setStatusCode(404);
  }

}
