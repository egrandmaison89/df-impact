<?php

declare(strict_types=1);

namespace Drupal\df_launch;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Site\Settings;

/**
 * Decides whether production analytics tags may load.
 */
final class LaunchEnvironment {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly Settings $settings,
  ) {}

  /**
   * Whether third-party launch tags should be injected on this request.
   */
  public function shouldInjectTags(): bool {
    $config = $this->configFactory->get('df_launch.settings');
    if (!$config->get('enabled')) {
      return FALSE;
    }
    return $this->isProductionContext();
  }

  /**
   * Production = Acquia prod or explicit settings flag (off by default).
   */
  public function isProductionContext(): bool {
    $ah = getenv('AH_SITE_ENVIRONMENT');
    if ($ah !== FALSE && $ah !== '') {
      return $ah === 'prod';
    }
    return $this->settings->get('df_launch_tags_enabled', FALSE) === TRUE;
  }

}
