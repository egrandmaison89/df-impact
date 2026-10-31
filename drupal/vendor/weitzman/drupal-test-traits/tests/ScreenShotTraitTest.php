<?php

namespace weitzman\DrupalTestTraits\Tests;

use weitzman\DrupalTestTraits\ExistingSiteWebDriverTestBase;
use weitzman\DrupalTestTraits\ScreenShotTrait;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * @coversDefaultClass \weitzman\DrupalTestTraits\ScreenShotTrait
 */
class ScreenShotTraitTest extends ExistingSiteSelenium2DriverTestBase
{
    use ScreenShotTrait;

    /**
     * Directory to test with.
     *
     * Randomized to allow repeated test runs if debugging.
     *
     * @var string
     */
    protected $directoryName;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->directoryName = '/tmp/' . mb_strtolower($this->randomMachineName());
    }

    /**
     * @covers ::captureScreenShot
     */
    public function testCaptureScreenShot()
    {
        putenv('DTT_SCREENSHOT_REPORT_DIRECTORY=' . $this->directoryName);
        $this->visit('/user');
        $this->captureScreenshot('my description');

        $this->assertDirectoryExists($this->directoryName);
        $files = glob($this->directoryName . '/*.png');
        $this->assertCount(1, $files);
        $this->assertStringContainsString('user', $files[0]);
        $this->assertStringContainsString('my-description', $files[0]);
    }
}
