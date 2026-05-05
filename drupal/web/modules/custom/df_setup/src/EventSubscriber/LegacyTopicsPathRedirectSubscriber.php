<?php

declare(strict_types=1);

namespace Drupal\df_setup\EventSubscriber;

use Drupal\Core\Url;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sends legacy /topics/{id-or-slug} URLs to /category/{slug} (WP parity).
 */
final class LegacyTopicsPathRedirectSubscriber implements EventSubscriberInterface {

  /**
   * Run before core routing (priority &gt; RouterListener) so orphaned paths work.
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::REQUEST => [['onKernelRequest', 52]]];
  }

  public function onKernelRequest(RequestEvent $event): void {
    if ($event->getRequestType() !== HttpKernelInterface::MAIN_REQUEST) {
      return;
    }

    $request = $event->getRequest();
    if (!preg_match('#^/topics/([^/]+)/?$#', $request->getPathInfo(), $matches)) {
      return;
    }

    $segment = mb_strtolower(rawurldecode($matches[1]), 'UTF-8');
    $term = NULL;
    if (ctype_digit($segment)) {
      $loaded = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load((int) $segment);
      if ($loaded instanceof TermInterface && $loaded->bundle() === 'topics') {
        $term = $loaded;
      }
    }
    else {
      $term = df_setup_topic_term_load_by_category_slug($segment);
    }

    if (!$term instanceof TermInterface || !$term->isPublished()) {
      return;
    }

    $canonical_path = '/category/' . df_setup_topic_category_slug($term->getName());
    $url = Url::fromUri('internal:' . $canonical_path, ['absolute' => TRUE]);
    $event->setResponse(new RedirectResponse($url->toString(), 301));
  }

}
