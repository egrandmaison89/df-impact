<?php

declare(strict_types=1);

namespace DrupalFinder;

/**
 * Minimal finder when drupal-finder is not installed at the project root.
 */
final class DrupalFinderComposerRuntime {

  public function getDrupalRoot(): string {
    return dirname(__DIR__, 4);
  }

  public function getVendorDir(): string {
    return dirname(__DIR__, 5) . '/vendor';
  }

}
