<?php

declare(strict_types=1);

namespace Drupal\Tests\df_setup\Unit;

use Drupal\df_setup\VideoEmbedUrl;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * @group df_setup
 */
#[Group('df_setup')]
final class VideoEmbedUrlTest extends TestCase {

  public function testYoutubeWatchAndShortUrls(): void {
    $this->assertSame(
      'https://www.youtube.com/embed/abc_123',
      VideoEmbedUrl::iframeSrc('https://www.youtube.com/watch?v=abc_123'),
    );
    $this->assertSame(
      'https://www.youtube.com/embed/abc_123',
      VideoEmbedUrl::iframeSrc('youtu.be/abc_123'),
    );
    $this->assertSame('youtube', VideoEmbedUrl::provider('https://www.youtube.com/watch?v=abc_123'));
  }

  public function testVimeoUrl(): void {
    $this->assertSame(
      'https://player.vimeo.com/video/987654',
      VideoEmbedUrl::iframeSrc('https://vimeo.com/987654'),
    );
    $this->assertSame('vimeo', VideoEmbedUrl::provider('https://vimeo.com/987654'));
  }

  public function testUnrelatedUrlHasNoIframe(): void {
    $this->assertNull(VideoEmbedUrl::iframeSrc('https://example.com/watch?v=1'));
  }

}
