<?php

// Use your module's testing namespace such as the one below.
namespace Drupal\Tests\moduleName\ExampleSelenium2DriverTest;

use Drupal\user\Entity\User;
use weitzman\DrupalTestTraits\ExistingSitePerformanceBase;

/**
 * A browser test suitable for testing Performance.
 */
class ExamplePerformanceTest extends ExistingSitePerformanceBase
{
    public function testPerformance()
    {
      // Create a taxonomy term. Will be automatically cleaned up at the end of the test.
        $admin = User::load(1);
        $admin->passRaw = 'password';
        $this->drupalLogin($admin);

      // @codingStandardsIgnoreStart
      // These lines are left here as examples of how to debug requests.
      // \weitzman\DrupalTestTraits\ScreenShotTrait::captureScreenshot();
      // $this->capturePageContent();
      // @codingStandardsIgnoreStop

      // Test autocomplete on article creation.
      $this->drupalGet('/node/add/article');
  }
}
