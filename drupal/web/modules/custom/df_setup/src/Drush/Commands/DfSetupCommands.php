<?php

declare(strict_types=1);

namespace Drupal\df_setup\Drush\Commands;

use Drupal\df_setup\WpInlineImageLocalizer;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * One-step content repairs for the Impact handoff.
 */
final class DfSetupCommands extends DrushCommands {

  public function __construct(
    private readonly WpInlineImageLocalizer $localizer,
  ) {
    parent::__construct();
  }

  /**
   * Download missing inline images and point body HTML at local files.
   */
  #[CLI\Command(name: 'df-setup:localize-wp-images', aliases: ['df-localize-images'])]
  #[CLI\Usage(name: 'drush df-setup:localize-wp-images', description: 'Rewrite wp-content image URLs in article and in-brief HTML to local files')]
  public function localizeWpImages(): void {
    $saved = $this->localizer->rewriteContentFields();
    $this->logger()->success(dt('Updated @count nodes.', ['@count' => $saved]));
  }

}
