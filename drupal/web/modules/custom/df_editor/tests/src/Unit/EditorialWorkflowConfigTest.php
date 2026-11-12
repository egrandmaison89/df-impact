<?php

declare(strict_types=1);

namespace Drupal\Tests\df_editor\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Editorial workflow transitions and the roles allowed to use them.
 *
 * Reads the exported config so this runs without a database.
 *
 * @group df_editor
 */
#[Group('df_editor')]
final class EditorialWorkflowConfigTest extends TestCase {

  /**
   * Config sync directory for this project.
   */
  private function syncDir(): string {
    $dir = dirname(__DIR__, 7) . '/config/sync';
    $this->assertDirectoryExists($dir);
    return $dir;
  }

  /**
   * @return array<string, mixed>
   */
  private function readYaml(string $filename): array {
    $data = Yaml::parseFile($this->syncDir() . '/' . $filename);
    $this->assertIsArray($data);
    return $data;
  }

  /**
   * Publish and send back both leave In Review, for the moderated bundles.
   */
  public function testPublishIncludesInReviewAndSendBackReturnsToDraft(): void {
    $workflow = $this->readYaml('workflows.workflow.editorial.yml');
    $transitions = $workflow['type_settings']['transitions'];

    $this->assertSame(['draft', 'in_review', 'published'], $transitions['publish']['from']);
    $this->assertSame('published', $transitions['publish']['to']);
    $this->assertSame(['in_review'], $transitions['send_back']['from']);
    $this->assertSame('draft', $transitions['send_back']['to']);
    $this->assertSame(['draft'], $transitions['submit_for_review']['from']);
    $this->assertSame('in_review', $transitions['submit_for_review']['to']);

    $bundles = $workflow['type_settings']['entity_types']['node'];
    foreach (['article', 'in_brief', 'issue'] as $bundle) {
      $this->assertContains($bundle, $bundles);
    }
  }

  /**
   * Web editors and site admins can publish from review and send it back.
   */
  public function testWebEditorAndSiteAdminCanPublishAndSendBack(): void {
    $web_editor = $this->readYaml('user.role.web_editor.yml')['permissions'];
    $site_admin = $this->readYaml('user.role.site_admin.yml')['permissions'];

    foreach (['use editorial transition publish', 'use editorial transition send_back'] as $permission) {
      $this->assertContains($permission, $web_editor);
      $this->assertContains($permission, $site_admin);
    }

    $this->assertNotContains('use editorial transition archive', $web_editor);
    foreach (['article', 'in_brief', 'issue'] as $bundle) {
      $this->assertNotContains("delete any $bundle content", $web_editor);
    }
  }

  /**
   * Revision history is visible. Revert and delete stay closed.
   */
  public function testRevisionViewWithoutRevertOrDelete(): void {
    foreach (['web_editor', 'site_admin'] as $role) {
      $permissions = $this->readYaml('user.role.' . $role . '.yml')['permissions'];
      $this->assertContains('view all revisions', $permissions);
      foreach (['article', 'in_brief', 'issue'] as $bundle) {
        $this->assertContains("view $bundle revisions", $permissions);
        $this->assertNotContains("revert $bundle revisions", $permissions);
        $this->assertNotContains("delete $bundle revisions", $permissions);
      }
      $this->assertNotContains('revert all revisions', $permissions);
      $this->assertNotContains('delete all revisions', $permissions);
    }
  }

}
