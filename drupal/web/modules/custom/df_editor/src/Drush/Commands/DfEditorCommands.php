<?php

declare(strict_types=1);

namespace Drupal\df_editor\Drush\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\df_editor\IssuePublisher;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for editor workflow verification.
 */
final class DfEditorCommands extends DrushCommands {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly IssuePublisher $issuePublisher,
  ) {
    parent::__construct();
  }

  /**
   * Creates a test issue + articles as web editor, publishes, asserts, cleans up.
   */
  #[CLI\Command(name: 'df-editor:test-publish-workflow')]
  #[CLI\Usage(name: 'df-editor:test-publish-workflow', description: 'Run publish workflow smoke test')]
  public function testPublishWorkflow(): void {
    $storage = $this->entityTypeManager->getStorage('node');
    $user_storage = $this->entityTypeManager->getStorage('user');

    $web_editor = $user_storage->loadByProperties(['name' => 'df_test_web_editor']);
    $web_editor = reset($web_editor);
    if (!$web_editor) {
      $web_editor = User::create([
        'name' => 'df_test_web_editor',
        'mail' => 'df_test_web_editor@example.com',
        'pass' => 'test',
        'status' => 1,
        'roles' => ['web_editor'],
      ]);
      $web_editor->save();
    }

    $before_latest = df_setup_get_latest_issue_nid();

    $issue = Node::create([
      'type' => 'issue',
      'title' => 'DF Test Issue Workflow',
      'field_season' => 'winter',
      'field_year' => 2099,
      'status' => 0,
      'moderation_state' => 'draft',
      'field_hero_heading' => 'Test Hero Heading',
    ]);
    $issue->save();
    $article_nids = [];
    for ($i = 1; $i <= 3; $i++) {
      $article = Node::create([
        'type' => 'article',
        'title' => "DF Test Article $i",
        'field_issue' => ['target_id' => $issue->id()],
        'status' => 0,
        'moderation_state' => 'draft',
        'body' => ['value' => 'Test body', 'format' => 'basic_html'],
      ]);
      $article->save();
      $article_nids[] = (int) $article->id();
    }

    $access = \Drupal::accessManager()->checkNamedRoute(
      'df_editor.site_settings',
      [],
      $web_editor,
      TRUE
    );
    if ($access->isAllowed()) {
      throw new \RuntimeException('Web editor must not access Impact site settings.');
    }

    $published_articles = $this->issuePublisher->publishIssueWithArticles($issue);
    if (count($published_articles) !== 3) {
      throw new \RuntimeException('Expected 3 published articles, got ' . count($published_articles));
    }

    $issue = $storage->load($issue->id());
    if (!$issue || !$issue->isPublished()) {
      throw new \RuntimeException('Issue was not published.');
    }

    $latest = df_setup_get_latest_issue_nid();
    if ((int) $latest !== (int) $issue->id()) {
      throw new \RuntimeException("Expected test issue to be latest ($issue->id()), got $latest");
    }

    $header_links = df_setup_get_header_issue_links(1);
    if ($header_links === [] || !str_contains($header_links[0]['title'], '2099')) {
      throw new \RuntimeException('Header issue links did not list the new test issue first.');
    }

    foreach ([$issue->id(), ...$article_nids] as $nid) {
      $node = $storage->load($nid);
      if ($node) {
        $node->delete();
      }
    }

    $this->logger()->success('df-editor:test-publish-workflow passed (latest issue restored to ' . ($before_latest ?? 'none') . ').');
  }

}
