<?php

declare(strict_types=1);

namespace Drupal\df_setup\EventSubscriber;

use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\df_setup\UnpublishedIssueLinks;
use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sends anonymous visitors from an unpublished issue to Browse issues.
 */
final class UnpublishedIssueRedirectSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::EXCEPTION => ['onException', 100]];
  }

  /**
   * Replaces the unpublished-issue 403 with a redirect.
   */
  public function onException(ExceptionEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $throwable = $event->getThrowable();
    if (!$throwable instanceof AccessDeniedHttpException) {
      return;
    }
    $node = $event->getRequest()->attributes->get('node');
    if (!$node instanceof NodeInterface || $node->bundle() !== 'issue' || $node->isPublished()) {
      return;
    }
    if (UnpublishedIssueLinks::isLegacyFixture($node)) {
      return;
    }
    if (UnpublishedIssueLinks::accountCanView(\Drupal::currentUser())) {
      return;
    }
    // Cache the redirect with the issue's tags. A plain redirect is stored
    // forever by the page cache and still sends people to Browse issues
    // after the issue is published.
    $response = new LocalRedirectResponse(UnpublishedIssueLinks::browseUrl(), 302);
    $response->addCacheableDependency($node);
    $response->getCacheableMetadata()->addCacheContexts(['user.permissions']);
    $event->setResponse($response);
    $event->stopPropagation();
  }

}
