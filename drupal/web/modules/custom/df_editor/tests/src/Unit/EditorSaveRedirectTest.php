<?php

declare(strict_types=1);

namespace Drupal\Tests\df_editor\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * @group df_editor
 */
#[Group('df_editor')]
final class EditorSaveRedirectTest extends TestCase {

  public function testSaveRedirectSkipsPreviewAndDeleteActions(): void {
    $module = file_get_contents(dirname(__DIR__, 3) . '/df_editor.module');
    $this->assertIsString($module);
    $this->assertStringContainsString("'df_publish_issue_bundle', 'preview', 'delete'", $module);
    $this->assertStringContainsString("in_array('preview', \$parents, TRUE)", $module);
  }

}
