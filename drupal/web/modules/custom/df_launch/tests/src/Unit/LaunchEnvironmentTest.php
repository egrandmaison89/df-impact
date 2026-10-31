<?php

declare(strict_types=1);

namespace Drupal\Tests\df_launch\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Site\Settings;
use Drupal\df_launch\LaunchEnvironment;
use PHPUnit\Framework\TestCase;

/**
 * @group df_launch
 */
final class LaunchEnvironmentTest extends TestCase {

  private ?string $previousAhEnv = NULL;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->previousAhEnv = getenv('AH_SITE_ENVIRONMENT') ?: NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if ($this->previousAhEnv === NULL) {
      putenv('AH_SITE_ENVIRONMENT');
    }
    else {
      putenv('AH_SITE_ENVIRONMENT=' . $this->previousAhEnv);
    }
    parent::tearDown();
  }

  private function environment(bool $enabled, bool $settingsFlag = FALSE): LaunchEnvironment {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['enabled', $enabled],
    ]);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with('df_launch.settings')->willReturn($config);
    $settingsArray = ['df_launch_tags_enabled' => $settingsFlag];
    $settings = new Settings($settingsArray);
    return new LaunchEnvironment($factory, $settings);
  }

  public function testTagsBlockedWhenConfigDisabled(): void {
    putenv('AH_SITE_ENVIRONMENT=prod');
    self::assertFalse($this->environment(FALSE)->shouldInjectTags());
  }

  public function testTagsBlockedOnAcquiaDevEvenWhenConfigEnabled(): void {
    putenv('AH_SITE_ENVIRONMENT=dev');
    self::assertFalse($this->environment(TRUE)->shouldInjectTags());
  }

  public function testTagsAllowedOnAcquiaProdWhenConfigEnabled(): void {
    putenv('AH_SITE_ENVIRONMENT=prod');
    self::assertTrue($this->environment(TRUE)->shouldInjectTags());
  }

  public function testTagsBlockedOnVmWithoutSettingsFlag(): void {
    putenv('AH_SITE_ENVIRONMENT');
    self::assertFalse($this->environment(TRUE)->shouldInjectTags());
  }

  public function testTagsAllowedWithSettingsFlagOnNonAcquiaProd(): void {
    putenv('AH_SITE_ENVIRONMENT');
    self::assertTrue($this->environment(TRUE, TRUE)->shouldInjectTags());
  }

}
