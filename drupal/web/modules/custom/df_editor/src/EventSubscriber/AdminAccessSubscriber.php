<?php

declare(strict_types=1);

namespace Drupal\df_editor\EventSubscriber;

use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Blocks web editors from site chrome and structural admin routes.
 */
final class AdminAccessSubscriber implements EventSubscriberInterface {

  /**
   * Routes web editors must not access (403).
   *
   * @var string[]
   */
  private const BLOCKED_ROUTE_PREFIXES = [
    'df_editor.',
    'df_site_snippets.',
    'block.admin_display',
    'block.admin_library',
    'entity.block_content_type.',
    'system.modules_list',
    'user.admin_create',
    'user.admin_permissions',
    'entity.user.collection',
    'layout_builder.',
  ];

  public function __construct(
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::REQUEST => ['onRequest', 30]];
  }

  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    if (!$this->currentUser->hasRole('web_editor') || $this->currentUser->hasPermission('administer df site settings')) {
      return;
    }
    $route = $event->getRequest()->attributes->get('_route');
    if (!is_string($route)) {
      return;
    }
    foreach (self::BLOCKED_ROUTE_PREFIXES as $prefix) {
      if (str_starts_with($route, $prefix)) {
        throw new AccessDeniedHttpException('This administration page is restricted to site administrators.');
      }
    }
  }

}
