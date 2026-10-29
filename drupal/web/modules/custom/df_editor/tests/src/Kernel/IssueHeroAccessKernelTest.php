<?php

declare(strict_types=1);

namespace Drupal\Tests\df_editor\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

/**
 * @group df_editor
 */
final class IssueHeroAccessKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'filter',
    'content_moderation',
    'workflows',
    'df_setup',
    'df_editor',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('system', ['sequences']);
    $this->installConfig(['df_editor']);
    if (file_exists($this->root . '/sites/default/files/sync/user.role.web_editor.yml')) {
      $this->installConfig(['user.role.web_editor']);
    }
    user_role_grant_permissions('authenticated', []);
  }

  /**
   * Web editors must not see homepage hero fields on the issue form.
   */
  public function testWebEditorIssueFormHidesHomepageHeroGroup(): void {
    if (!\Drupal::entityTypeManager()->getStorage('node_type')->load('issue')) {
      $this->markTestSkipped('Issue node type not installed in this kernel profile.');
    }
    $editor = User::create([
      'name' => 'kernel_web_editor',
      'mail' => 'kernel_web_editor@example.com',
      'status' => 1,
      'roles' => ['web_editor'],
    ]);
    $editor->save();

    $issue = Node::create([
      'type' => 'issue',
      'title' => 'Kernel test issue',
      'status' => 1,
    ]);
    $issue->save();

    \Drupal::service('account_switcher')->switchTo($editor);
    $form_object = \Drupal::entityTypeManager()
      ->getFormObject('node', 'default')
      ->setEntity($issue);
    $form = \Drupal::formBuilder()->getForm($form_object);
    \Drupal::service('account_switcher')->switchBack();

    $this->assertArrayHasKey('df_homepage_hero', $form);
    $this->assertFalse($form['df_homepage_hero']['#access'] ?? TRUE);
    $this->assertArrayNotHasKey('field_hero_heading', $form);
    $this->assertArrayNotHasKey('field_hero_video', $form);
  }

}
