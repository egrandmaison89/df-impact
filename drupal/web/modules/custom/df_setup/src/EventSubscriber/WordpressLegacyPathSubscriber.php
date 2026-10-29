<?php

declare(strict_types=1);

namespace Drupal\df_setup\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
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
    // Intentionally empty — no 301 redirects for manifest parity URLs.
  }

}
