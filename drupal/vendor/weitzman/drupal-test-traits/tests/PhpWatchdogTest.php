<?php

namespace Drupal\Tests\moduleName\ExistingSite;

use weitzman\DrupalTestTraits\Exception\PhpWatchdogException;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Tests PHP warning/notices detection when executing tests.
 */
class PhpWatchdogTest extends ExistingSiteBase
{
    /**
     * Checks that our flag avoids failure if PHP warnings are thrown during the test.
     * It is not possible to test the inverse, as exceptions during teardown are an irreversible test failure.
     */
    public function testPhpWatchdogException()
    {
        // Disable the check
        $this->failOnPhpWatchdogMessages = false;
        // Simulate a PHP warning in watchdog.
        _drupal_error_handler(E_WARNING, "Testing a PHP warning.");
        // Perform an assertion or else PHPUnit considers us 'risky'.
        $this->assertTrue(true);
    }
}
