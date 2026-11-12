<?php

declare(strict_types=1);

namespace Drupal\Tests\df_editor\Unit;

use Drupal\df_editor\EditorBodyImages;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * @group df_editor
 */
#[Group('df_editor')]
final class EditorBodyImagesTest extends TestCase {

  public function testUploadBecomesAlignedCaptionedImage(): void {
    $input = '<p><img src="/files/photo.jpg" data-entity-type="file" data-entity-uuid="abc" alt="Palm"></p>';
    $output = EditorBodyImages::normalize($input);
    $this->assertStringNotContainsString('<p>', $output);
    $this->assertStringContainsString('data-align="right"', $output);
    $this->assertStringContainsString('data-caption=" "', $output);
    $this->assertStringContainsString('data-entity-uuid="abc"', $output);
  }

  public function testMigratedWpBlockIsLeftAlone(): void {
    $input = '<!-- wp:image {"align":"right"} --><figure class="wp-block-image alignright"><img src="/files/a.jpg" alt=""></figure><!-- /wp:image -->';
    $this->assertSame($input, EditorBodyImages::normalize($input));
  }

  public function testExistingCaptionAndFigureStay(): void {
    $input = '<figure class="wp-block-image alignleft"><img src="/files/a.jpg" data-entity-uuid="abc" data-caption="Already" alt=""></figure>';
    $this->assertSame($input, EditorBodyImages::normalize($input));
  }

  public function testMediaEmbedGetsAlignAndCaption(): void {
    $input = '<drupal-media data-entity-type="media" data-entity-uuid="media-1"></drupal-media>';
    $output = EditorBodyImages::normalize($input);
    $this->assertStringContainsString('data-align="right"', $output);
    $this->assertStringContainsString('data-caption=" "', $output);
  }

}
