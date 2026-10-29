<?php

declare(strict_types=1);

namespace Drupal\Tests\df_editor\ExistingSite;

use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * End-to-end editor workflow on the bootstrapped site (DTT).
 *
 * @group df_editor
 */
#[Group('df_editor')]
final class IssuePublishWorkflowTest extends ExistingSiteBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    if (!\Drupal::moduleHandler()->moduleExists('df_editor')) {
      $this->markTestSkipped('df_editor must be enabled.');
    }
  }

  /**
   * Web editor publish flow updates public listings; admin routes stay forbidden.
   */
  public function testWebEditorPublishIssueUpdatesPublicSite(): void {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $before_latest = function_exists('df_setup_get_latest_issue_nid') ? df_setup_get_latest_issue_nid() : NULL;

    $editor = User::create([
      'name' => 'func_test_web_editor_' . $this->randomMachineName(4),
      'mail' => 'func_test@example.com',
      'pass' => 'test-pass',
      'status' => 1,
      'roles' => ['web_editor'],
    ]);
    $editor->save();
    $this->markEntityForCleanup($editor);

    $issue = Node::create([
      'type' => 'issue',
      'title' => 'Functional Test Issue 2099',
      'field_season' => 'winter',
      'field_year' => 2099,
      'status' => 0,
      'moderation_state' => 'draft',
      'field_hero_heading' => 'Functional Hero Heading',
    ]);
    $issue->save();
    $this->markEntityForCleanup($issue);

    $articles = [];
    for ($i = 1; $i <= 2; $i++) {
      $articles[] = Node::create([
        'type' => 'article',
        'title' => "Functional Test Article $i",
        'field_issue' => ['target_id' => $issue->id()],
        'status' => 0,
        'moderation_state' => 'draft',
        'body' => ['value' => 'Body', 'format' => 'full_html'],
      ]);
      $articles[$i - 1]->save();
      $this->markEntityForCleanup($articles[$i - 1]);
    }

    $this->drupalLogin($editor);

    $this->drupalGet(Url::fromRoute('df_editor.site_settings'));
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet(Url::fromRoute('df_editor.footer_settings'));
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet(Url::fromRoute('df_editor.homepage_hero'));
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet(Url::fromRoute('df_editor.issue_dashboard'));
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalGet($issue->toUrl('edit-form'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseNotContains('id="edit-df-homepage-hero"');

    $this->drupalLogout();

    /** @var \Drupal\df_editor\IssuePublisher $publisher */
    $publisher = \Drupal::service('df_editor.issue_publisher');
    $publisher->publishIssueWithArticles($issue);

    $this->assertSame((int) $issue->id(), df_setup_get_latest_issue_nid());

    $header = df_setup_get_header_issue_links(1);
    $this->assertNotEmpty($header);
    $this->assertStringContainsString('2099', $header[0]['title']);

    $primary = \Drupal::service('df_editor.current_issue_nav')->applyCurrentIssueUrl([
      ['title' => 'Current Issue', 'url' => '/browse-issues'],
    ]);
    $this->assertSame($issue->toUrl()->toString(), $primary[0]['url']);

    $this->drupalGet(Url::fromRoute('<front>'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('Functional Hero Heading');

    $this->drupalGet('/browse-issues');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('Functional Test Issue 2099');

    foreach ([$issue, ...$articles] as $node) {
      $loaded = $storage->load($node->id());
      if ($loaded) {
        $loaded->delete();
      }
    }

    if ($before_latest !== NULL) {
      $this->assertSame($before_latest, df_setup_get_latest_issue_nid());
    }
  }

  /**
   * Web editors must not see homepage hero fields on the issue form.
   */
  public function testWebEditorIssueFormHidesHomepageHeroGroup(): void {
    $editor = User::create([
      'name' => 'kernel_style_web_editor_' . $this->randomMachineName(4),
      'mail' => 'hero_access@example.com',
      'status' => 1,
      'roles' => ['web_editor'],
    ]);
    $editor->save();
    $this->markEntityForCleanup($editor);

    $issue = Node::create([
      'type' => 'issue',
      'title' => 'Hero access test issue',
      'field_season' => 'spring',
      'field_year' => 2098,
      'status' => 0,
      'moderation_state' => 'draft',
    ]);
    $issue->save();
    $this->markEntityForCleanup($issue);

    \Drupal::service('account_switcher')->switchTo($editor);
    $form_object = \Drupal::entityTypeManager()
      ->getFormObject('node', 'default')
      ->setEntity($issue);
    $form = \Drupal::formBuilder()->getForm($form_object);
    \Drupal::service('account_switcher')->switchBack();

    $this->assertArrayHasKey('df_homepage_hero', $form);
    $this->assertFalse($form['df_homepage_hero']['#access'] ?? TRUE);
    $this->assertArrayNotHasKey('field_hero_heading', $form);
  }

}
