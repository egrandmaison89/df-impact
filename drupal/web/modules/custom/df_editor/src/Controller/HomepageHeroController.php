<?php

declare(strict_types=1);

namespace Drupal\df_editor\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\df_editor\CurrentIssueNav;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Redirects site admins to the current issue hero fields.
 */
final class HomepageHeroController extends ControllerBase {

  public function __construct(
    private readonly CurrentIssueNav $currentIssueNav,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('df_editor.current_issue_nav'),
    );
  }

  /**
   * Redirect to latest issue edit form (homepage hero field group).
   */
  public function editRedirect(): TrustedRedirectResponse|array {
    $url = $this->currentIssueNav->homepageHeroEditUrl();
    if ($url === NULL) {
      $this->messenger()->addWarning($this->t('No published issue is available yet. Create and publish an issue first, then return here.'));
      return $this->redirect('df_editor.site_settings');
    }
    return new TrustedRedirectResponse($url->setAbsolute()->toString());
  }

}
