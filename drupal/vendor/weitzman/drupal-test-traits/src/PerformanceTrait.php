<?php

namespace weitzman\DrupalTestTraits;

use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Exception\DriverException;
use Drupal\Core\Cache\Cache;
use Drupal\Tests\PerformanceTestTrait;

trait PerformanceTrait
{
    use PerformanceTestTrait;

    /**
     * Adds the required driver arguments to enable the chrome performance log.
     *
     * @throws \Behat\Mink\Exception\DriverException
     *   Thrown when invalid arguments are passed.
     */
    protected function getDriverInstance(): DriverInterface
    {
        if (!isset($this->driver) && ($driverArgs = getenv('DTT_MINK_DRIVER_ARGS') ?: '["firefox", null, "http://localhost:4444/wd/hub"]')) {
            $driverArgs = json_decode($driverArgs, true);
            if (!$driverArgs) {
                throw new DriverException('Invalid driver arguments given for "DTT_MINK_DRIVER_ARGS", make sure to use double quotes inside the brackets.');
            }
            $driverArgs[1]['goog:loggingPrefs'] = [
                'browser' => 'ALL',
                'performance' => 'ALL',
                'performanceTimeline' => 'ALL',
            ];
            // Support legacy key.
            $driverArgs[1]['goog:chromeOptions']['perfLoggingPrefs'] = [
              'traceCategories' => 'timeline,devtools.timeline,browser',
            ];

            $this->driver = new Selenium2Driver(...$driverArgs);
        }
        return $this->driver;
    }

    /**
     * Clear caches.
     */
    protected function clearCaches(): void
    {
        foreach (Cache::getBins() as $bin) {
            $bin->deleteAll();
        }
    }
}
