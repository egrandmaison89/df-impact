<?php

declare(strict_types=1);

namespace Drupal\Tests\df_editor\Unit;

use Drupal\df_editor\Plugin\Filter\EditorHtmlIframeFilter;
use Drupal\df_editor\Plugin\Filter\EditorHtmlSanitizeFilter;
use Drupal\filter\FilterProcessResult;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * @group df_editor
 */
#[Group('df_editor')]
final class EditorHtmlFilterTest extends TestCase {

  /**
   * Script tags and onclick handlers are removed.
   */
  public function testSanitizeStripsScriptAndEventHandlers(): void {
    $filter = new EditorHtmlSanitizeFilter([], 'editor_html_sanitize', ['provider' => 'df_editor']);
    $input = '<p onclick="alert(1)">Hi</p><script>alert("x")</script><p>Ok</p>';
    $result = $filter->process($input, 'en');
    $this->assertInstanceOf(FilterProcessResult::class, $result);
    $out = (string) $result;
    $this->assertStringNotContainsString('<script', $out);
    $this->assertStringNotContainsString('onclick', $out);
    $this->assertStringContainsString('Ok', $out);
  }

  /**
   * Iframe filter keeps Vimeo/YouTube and drops other hosts.
   */
  public function testIframeHostAllowlist(): void {
    $filter = new EditorHtmlIframeFilter([], 'editor_html_iframe', ['provider' => 'df_editor']);
    $input = '<div><iframe src="https://player.vimeo.com/video/1"></iframe><iframe src="https://evil.example/embed"></iframe><iframe src="https://www.youtube.com/embed/abc"></iframe></div>';
    $result = $filter->process($input, 'en');
    $out = (string) $result;
    $this->assertStringContainsString('player.vimeo.com', $out);
    $this->assertStringContainsString('youtube.com', $out);
    $this->assertStringNotContainsString('evil.example', $out);
  }

}
