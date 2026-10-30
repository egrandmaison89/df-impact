<?php

namespace weitzman\DrupalTestTraits;

use Drupal\FunctionalJavascriptTests\JSWebAssert;

/**
 * You may use this class in any of these ways:
 * - Copy its code into your own base class.
 * - Have your base class extend this class.
 * - Your tests may extend this class directly.
 *
 * This class uses the WebDriver protocol to interact with browsers. It can
 * be used with any system that implements webdriver, such as chromedriver or
 * Selenium.
 */
abstract class ExistingSiteSelenium2DriverTestBase extends ExistingSiteBase
{
    use Selenium2DriverTrait;

    /**
     * {@inheritdoc}
     */
    public function assertSession($name = null)
    {
        // Ensure that the test is not marked as risky because of no assertions. In
        // PHPUnit 6 tests that only make assertions using $this->assertSession()
        // can be marked as risky.
        $this->addToAssertionCount(1);
        return new JSWebAssert($this->getSession($name), $this->baseUrl);
    }
}
