<?php

declare(strict_types=1);

namespace Drupal\df_editor\Drush\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\df_editor\CurrentIssueNav;
use Drupal\df_editor\IssuePublisher;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use GuzzleHttp\Client;

/**
 * Drush commands for editor workflow verification.
 */
final class DfEditorCommands extends DrushCommands {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly IssuePublisher $issuePublisher,
    private readonly CurrentIssueNav $currentIssueNav,
  ) {
    parent::__construct();
  }

  /**
   * Integration test: web editor workflow, public listings, and access denials.
   */
  #[CLI\Command(name: 'df-editor:test-publish-workflow')]
  #[CLI\Usage(name: 'df-editor:test-publish-workflow', description: 'Run publish workflow integration test')]
  public function testPublishWorkflow(): void {
    $storage = $this->entityTypeManager->getStorage('node');
    $user_storage = $this->entityTypeManager->getStorage('user');
    $base = rtrim(getenv('DRUPAL_BASE_URL') ?: 'http://127.0.0.1:8888', '/');
    $http = new Client(['http_errors' => FALSE, 'timeout' => 30]);

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
    for ($i = 1; $i <= 2; $i++) {
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

    foreach (['df_editor.site_settings', 'df_editor.footer_settings', 'df_editor.homepage_hero'] as $route) {
      $access = \Drupal::accessManager()->checkNamedRoute($route, [], $web_editor, TRUE);
      if ($access->isAllowed()) {
        throw new \RuntimeException("Web editor must not access route $route.");
      }
    }

    $form_object = \Drupal::entityTypeManager()
      ->getFormObject('node', 'default')
      ->setEntity($issue);
    \Drupal::service('account_switcher')->switchTo($web_editor);
    $form = \Drupal::formBuilder()->getForm($form_object);
    \Drupal::service('account_switcher')->switchBack();
    if (isset($form['df_homepage_hero']) && ($form['df_homepage_hero']['#access'] ?? TRUE) !== FALSE) {
      throw new \RuntimeException('Web editor must not see df_homepage_hero on the issue form.');
    }
    if (!isset($form['field_hero_heading'])) {
      throw new \RuntimeException('Web editor must see field_hero_heading on the issue form.');
    }
    foreach (['field_hero_video', 'field_hero_poster'] as $key) {
      if (isset($form[$key])) {
        throw new \RuntimeException("Web editor must not see top-level $key on the issue form.");
      }
    }

    $published_articles = $this->issuePublisher->publishIssueWithArticles($issue);
    if (count($published_articles) !== 2) {
      throw new \RuntimeException('Expected 2 published articles, got ' . count($published_articles));
    }

    $issue = $storage->load($issue->id());
    if (!$issue || !$issue->isPublished()) {
      throw new \RuntimeException('Issue was not published.');
    }

    if ((int) df_setup_get_latest_issue_nid() !== (int) $issue->id()) {
      throw new \RuntimeException('Published test issue must be the latest issue.');
    }

    $header_links = df_setup_get_header_issue_links(1);
    if ($header_links === [] || !str_contains($header_links[0]['title'], '2099')) {
      throw new \RuntimeException('Header Issues dropdown did not list the test issue first.');
    }

    $primary = $this->currentIssueNav->applyCurrentIssueUrl([
      ['title' => 'Current Issue', 'url' => '/browse-issues'],
    ]);
    if ($primary[0]['url'] !== $issue->toUrl()->toString()) {
      throw new \RuntimeException('Current Issue link did not resolve to the latest issue URL.');
    }

    $front = (string) $http->get($base . '/')->getBody();
    if (!str_contains($front, 'Test Hero Heading')) {
      throw new \RuntimeException('Homepage hero heading missing on front page.');
    }

    $browse = (string) $http->get($base . '/browse-issues')->getBody();
    if (!str_contains($browse, 'DF Test Issue Workflow')) {
      throw new \RuntimeException('Browse issues page missing test issue title.');
    }

    foreach ([$issue->id(), ...$article_nids] as $nid) {
      $node = $storage->load($nid);
      if ($node) {
        $node->delete();
      }
    }

    $this->logger()->success('df-editor:test-publish-workflow passed (latest issue restored to ' . ($before_latest ?? 'none') . ').');
  }

  /**
   * Eric editor E2E: Winter 2027 issue, public surfaces, RBAC, cleanup.
   */
  #[CLI\Command(name: 'df-editor:test-issue-e2e')]
  #[CLI\Usage(name: 'df-editor:test-issue-e2e', description: 'Run full web_editor issue publish verification')]
  public function testIssueE2e(): void {
    $script = DRUPAL_ROOT . '/modules/custom/df_migrate/scripts/test_web_editor_issue_e2e.php';
    if (!is_readable($script)) {
      throw new \RuntimeException('Missing test_web_editor_issue_e2e.php');
    }
    if (!defined('DF_EDITOR_E2E_SKIP_AUTO_RUN')) {
      define('DF_EDITOR_E2E_SKIP_AUTO_RUN', TRUE);
    }
    require_once $script;
    $results = df_migrate_web_editor_issue_e2e();
    foreach ($results as $check => $passed) {
      $label = $passed ? 'PASS' : 'FAIL';
      $this->logger()->notice("$label $check");
      if (!$passed) {
        throw new \RuntimeException("Check failed: $check");
      }
    }
    $this->logger()->success('df-editor:test-issue-e2e passed (' . count($results) . ' checks).');
  }

}
