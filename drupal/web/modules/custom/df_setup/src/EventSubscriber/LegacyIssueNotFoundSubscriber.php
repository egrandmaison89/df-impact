<?php

declare(strict_types=1);

namespace Drupal\df_setup\EventSubscriber;

use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sets HTTP 404 on an issue URL only when that issue is not published.
 *
 * Late Fall 2022 used to be flagged as a WordPress archive error. A published
 * issue node renders its issue page instead.
 */
final class LegacyIssueNotFoundSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::RESPONSE => ['onResponse', -10]];
  }

  /**
   * Marks an unpublished legacy issue response as 404.
   *
   * A published issue is left alone, including Late Fall 2022.
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
    if (!$node instanceof NodeInterface || $node->bundle() !== 'issue' || $node->isPublished()) {
      return;
    }
    $alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $node->id());
    if (df_setup_legacy_issue_tag_slug_for_request_path($alias) === '') {
      return;
    }
    $event->getResponse()->setStatusCode(404);
  }

}
