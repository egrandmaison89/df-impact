<?php

namespace weitzman\DrupalTestTraits;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Logger\RfcLoggerTrait;
use weitzman\DrupalTestTraits\Exception\LoggedMessageException;

/**
 * Trait for causing tests to automatically fail if errors are logged.
 *
 * Test classes using this trait must declare that they implement Psr\Log\LoggerInterface.
 *
 * Usage:
 *   From ::setUp call $this->failOnLoggedErrors()
 *
 * weitzman\DrupalTestTraits\ExistingSiteBase::tearDown() calls $this->ignoreLoggedErrors().
 */
trait LoggerTrait
{
    use RfcLoggerTrait;

    /**
     * @var int
     */
    protected $failOnLogsLevel = RfcLogLevel::ERROR;

    /**
     * {@inheritdoc}
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if ($level <= $this->failOnLogsLevel) {
            throw new LoggedMessageException(strtr($message, $context), $level);
        }
    }

    /**
     *  Set up tests to fail if errors are logged.
     *
     * @param int $level
     *   (optional) The rfc log severity level.
     */
    protected function failOnLoggedErrors($level = null)
    {
        // Make this class into a logger, so it will be called every time something needs logging.
        $this->container->get('logger.factory')->addLogger($this);
        if (!is_null($level)) {
            $this->setFailOnLogsLevel($level);
        }
    }

    /*
     * Ignore any logs, do not fail tests.
     */
    protected function ignoreLoggedErrors()
    {
        $levels = array_keys(RfcLogLevel::getLevels());
        sort($levels);
        $this->setFailOnLogsLevel($levels[0]);
    }

    /*
     * Specify the log severity to fail on.
     *
     * @param int $level
     *   The rfc log severity level.
     */
    protected function setFailOnLogsLevel($level)
    {
        $this->failOnLogsLevel = $level;
    }
}
