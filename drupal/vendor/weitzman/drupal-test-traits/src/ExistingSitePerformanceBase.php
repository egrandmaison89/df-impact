<?php

declare(strict_types=1);

namespace weitzman\DrupalTestTraits;

/**
 * Base class for performance testing with Drupal Test Traits.
 */
class ExistingSitePerformanceBase extends ExistingSiteSelenium2DriverTestBase
{
    use PerformanceTrait;

    /**
     * Must be set for PerformanceTestTrait to log database queries.
     */
    protected string $databasePrefix = '';

    protected function assertPreConditions(): void
    {
        parent::assertPreConditions();
        $this->assertTrue(\Drupal::service('module_handler')->moduleExists('performance_test'), 'The performance_test module must be enabled. In order to discover it, append to settings.php: $settings[\'extension_discovery_scan_tests\'] = TRUE');
    }
}
