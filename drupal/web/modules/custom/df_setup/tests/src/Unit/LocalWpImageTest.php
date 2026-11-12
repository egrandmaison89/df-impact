<?php

declare(strict_types=1);

namespace Drupal\Tests\df_setup\Unit;

use Drupal\df_setup\LocalWpImage;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\df_setup\LocalWpImage
 */
final class LocalWpImageTest extends TestCase {

  private string $dir;

  protected function setUp(): void {
    parent::setUp();
    $this->dir = sys_get_temp_dir() . '/df-local-wp-' . bin2hex(random_bytes(4));
    mkdir($this->dir);
  }

  protected function tearDown(): void {
    foreach (glob($this->dir . '/*') ?: [] as $path) {
      unlink($path);
    }
    rmdir($this->dir);
    parent::tearDown();
  }

  public function testBasenameIgnoresDatedFolders(): void {
    $url = 'https://i' . '0.wp.com/danafarberimpact.org/wp-content/uploads/2024/03/Ellison.jpg?fit=870%2C570';
    $this->assertSame('Ellison.jpg', LocalWpImage::basenameFromUrl($url));
  }

  public function testHtmlStubIsNotAnImage(): void {
    $path = $this->dir . '/stub.jpg';
    file_put_contents($path, '<html><head><link rel="icon"');
    $this->assertFalse(LocalWpImage::isImageFile($path));
    $this->assertNull(LocalWpImage::findInDirectory($this->dir, 'stub.jpg'));
  }

  public function testSizeVariantIsUsedWhenTheFullFileIsAStub(): void {
    file_put_contents($this->dir . '/Marx.jpg', '<html>');
    file_put_contents($this->dir . '/Marx-1024x734.jpg', "\xFF\xD8\xFF" . str_repeat('a', 32));
    $found = LocalWpImage::findInDirectory($this->dir, 'Marx.jpg');
    $this->assertNotNull($found);
    $this->assertStringEndsWith('Marx-1024x734.jpg', $found);
  }

  public function testExactRealFileWins(): void {
    file_put_contents($this->dir . '/Runyon.png', "\x89PNG\r\n\x1a\n" . str_repeat('b', 16));
    file_put_contents($this->dir . '/Runyon-1024x734.png', "\x89PNG\r\n\x1a\n" . str_repeat('c', 16));
    $found = LocalWpImage::findInDirectory($this->dir, 'Runyon.png');
    $this->assertNotNull($found);
    $this->assertStringEndsWith('/Runyon.png', $found);
  }

  public function testCaseDifferenceResolvesToTheExtensionedFile(): void {
    file_put_contents($this->dir . '/Impact_About_YawkeyBuilding_725.jpg', "\xFF\xD8\xFF" . str_repeat('y', 32));
    $found = LocalWpImage::findInDirectory($this->dir, 'impact_about_yawkeybuilding_725');
    $this->assertNotNull($found);
    $this->assertStringEndsWith('Impact_About_YawkeyBuilding_725.jpg', $found);
  }

  public function testExtensionlessOriginalLosesToTheExtensionedSibling(): void {
    file_put_contents($this->dir . '/impact-inbrief10-1450', "\xFF\xD8\xFF" . str_repeat('a', 32));
    file_put_contents($this->dir . '/Impact-InBrief10-1450.jpg', "\xFF\xD8\xFF" . str_repeat('b', 32));
    $found = LocalWpImage::findInDirectory($this->dir, 'impact-inbrief10-1450');
    $this->assertNotNull($found);
    $this->assertStringEndsWith('Impact-InBrief10-1450.jpg', $found);
  }

  public function testExtensionlessImageIsCopiedWithAnExtension(): void {
    file_put_contents($this->dir . '/lonely-1450', "\xFF\xD8\xFF" . str_repeat('c', 32));
    $found = LocalWpImage::findInDirectory($this->dir, 'lonely-1450');
    $this->assertNotNull($found);
    $this->assertStringEndsWith('lonely-1450.jpg', $found);
    $this->assertFileExists($found);
  }

}
