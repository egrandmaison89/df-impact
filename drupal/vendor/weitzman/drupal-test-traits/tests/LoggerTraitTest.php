<?php

namespace weitzman\DrupalTestTraits\Tests;

use weitzman\DrupalTestTraits\ExistingSiteBase;
use Drupal\Core\Logger\RfcLogLevel;

/**
 * @coversDefaultClass \weitzman\DrupalTestTraits\LoggerTrait
 */
class LoggerTraitTest extends ExistingSiteBase
{
    /**
     * Logger trait is opt-in.
     *
     * @doesNotPerformAssertions
     */
    public function testOptIn()
    {
        \Drupal::logger('drupal_test_traits_test')->error('This should not cause a test failure because ::failOnLoggedErrors() has not been called');
    }

    /**
     * Logged errors throw an exception by default.
     *
     * @covers ::failOnLoggedErrors
     */
    public function testError()
    {
        $this->failOnLoggedErrors();
        $message = 'This should now cause a test failure.';
        $this->expectExceptionCode(RfcLogLevel::ERROR);
        $this->expectExceptionMessage($message);
        \Drupal::logger('drupal_test_traits_test')->error($message);
    }

    /**
     * Logged warnings are ignored by default.
     *
     * @covers ::failOnLoggedErrors
     * @doesNotPerformAssertions
     */
    public function testWarning()
    {
        $this->failOnLoggedErrors();
        \Drupal::logger('drupal_test_traits_test')->warning('This should not cause a test failure because it is a warning');
    }

    /**
     * When logs of different kinds occur, only those of the specified severity throw an exception.
     *
     * @covers ::failOnLoggedErrors
     */
    public function testWarningsAndError()
    {
        $this->failOnLoggedErrors();
        \Drupal::logger('drupal_test_traits_test')->warning('This should not cause a test failure.');
        $message = 'This should cause a test failure.';
        $this->expectExceptionCode(RfcLogLevel::ERROR);
        $this->expectExceptionMessage($message);
        \Drupal::logger('drupal_test_traits_test')->error($message);
        \Drupal::logger('drupal_test_traits_test')->warning('This should not cause a test failure.');
    }

    /**
     * Logs can be ignored.
     *
     * @covers ::ignoreLoggedErrors
     * @doesNotPerformAssertions
     */
    public function testIgnoreLoggedErrors()
    {
        $this->failOnLoggedErrors();
        $this->ignoreLoggedErrors();
        \Drupal::logger('drupal_test_traits_test')->error('This should not cause a test failure because we have asked for failures to be ignored');
    }

    /**
     * The log severity can be specified.
     *
     * @covers ::setFailOnLogsLevel
     */
    public function testFailOnWarning()
    {
        $this->failOnLoggedErrors();
        $message = 'This should now cause a test failure.';
        $this->setFailOnLogsLevel(RfcLogLevel::WARNING);
        $this->expectExceptionCode(RfcLogLevel::WARNING);
        $this->expectExceptionMessage($message);
        \Drupal::logger('drupal_test_traits_test')->warning($message);
    }
}
