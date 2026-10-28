<?php

declare(strict_types=1);

namespace Drupal\df_setup\EventSubscriber;

use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * WordPress legacy paths: /about, /home, /category/digital-exclusives.
 */
final class WordpressLegacyPathSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [KernelEvents::REQUEST => [['onRequest', 40]]];
  }

  public function onRequest(RequestEvent $event): void {
    if ($event->getRequestType() !== HttpKernelInterface::MAIN_REQUEST) {
      return;
    }
    $path = rtrim($event->getRequest()->getPathInfo(), '/') ?: '/';
    $lower = mb_strtolower($path, 'UTF-8');

    $target = match ($lower) {
      '/about' => '/about-impact',
      '/home' => '/homepage',
      '/category/digital-exclusives' => '/digital-exclusives',
      default => NULL,
    };

    if ($target === NULL) {
      return;
    }

    $url = Url::fromUri('internal:' . $target, ['absolute' => TRUE]);
    $event->setResponse(new RedirectResponse($url->toString(), 301));
  }

}
