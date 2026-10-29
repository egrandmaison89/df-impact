<?php

/**
 * @file
 * Eric editor workflow E2E: Winter 2027 issue + 3 articles, public checks, RBAC.
 *
 * Usage: drush scr web/modules/custom/df_migrate/scripts/test_web_editor_issue_e2e.php
 */

declare(strict_types=1);

use Drupal\Core\Url;
use Drupal\df_editor\IssuePublisher;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\Request;

/**
 * @return array<string, bool>
 */
function df_migrate_web_editor_issue_e2e(): array {
  $results = [];
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $user_storage = \Drupal::entityTypeManager()->getStorage('user');
  $base = rtrim(getenv('DRUPAL_BASE_URL') ?: 'http://127.0.0.1:8888', '/');
  $http = new Client(['http_errors' => FALSE, 'timeout' => 45]);
  $kernel_html = static function (string $path): string {
    $request = Request::create($path, 'GET');
    $response = \Drupal::service('http_kernel')->handle($request);
    $html = (string) $response->getContent();
    \Drupal::service('http_kernel')->terminate($request, $response);
    return $html;
  };
  $created_nids = [];

  $fail = static function (string $key, string $message) use (&$results): void {
    $results[$key] = FALSE;
    throw new \RuntimeException("[$key] $message");
  };

  $pass = static function (string $key) use (&$results): void {
    $results[$key] = TRUE;
  };

  try {
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

    $site_admin = $user_storage->loadByProperties(['name' => 'df_test_site_admin']);
    $site_admin = reset($site_admin);
    if (!$site_admin) {
      $site_admin = User::create([
        'name' => 'df_test_site_admin',
        'mail' => 'df_test_site_admin@example.com',
        'pass' => 'test',
        'status' => 1,
        'roles' => ['site_admin'],
      ]);
      $site_admin->save();
    }

    foreach (['df_editor.site_settings', 'df_editor.footer_settings', 'df_editor.homepage_hero'] as $route) {
      $access = \Drupal::accessManager()->checkNamedRoute($route, [], $web_editor, TRUE);
      if ($access->isAllowed()) {
        $fail('web_editor_cannot_edit_site_chrome', "web_editor must not access $route");
      }
    }
    $pass('web_editor_cannot_edit_site_chrome');

    foreach (['df_editor.site_settings', 'df_editor.footer_settings', 'df_editor.homepage_hero'] as $route) {
      $access = \Drupal::accessManager()->checkNamedRoute($route, [], $site_admin, TRUE);
      if (!$access->isAllowed()) {
        $fail('site_admin_can_edit_site_chrome', "site_admin must access $route");
      }
    }
    $pass('site_admin_can_edit_site_chrome');

    $cover_source = $storage->load(df_setup_get_latest_issue_nid() ?? 0);
    $account_switcher = \Drupal::service('account_switcher');
    $account_switcher->switchTo($web_editor);

    $hero_heading = 'Winter 2027 E2E Hero Heading';
    $issue = Node::create([
      'type' => 'issue',
      'title' => 'Winter 2027 Issue',
      'field_season' => 'winter',
      'field_year' => 2027,
      'status' => 0,
      'moderation_state' => 'draft',
      'field_hero_heading' => $hero_heading,
    ]);
    if ($cover_source instanceof NodeInterface && $cover_source->hasField('field_cover_image') && !$cover_source->get('field_cover_image')->isEmpty()) {
      $issue->set('field_cover_image', $cover_source->get('field_cover_image')->getValue());
    }
    $issue->save();
    $created_nids[] = (int) $issue->id();

    $article_titles = [];
    for ($i = 1; $i <= 3; $i++) {
      $title = "Winter 2027 E2E Article $i";
      $article_titles[] = $title;
      $article = Node::create([
        'type' => 'article',
        'title' => $title,
        'field_issue' => ['target_id' => $issue->id()],
        'status' => 0,
        'moderation_state' => 'draft',
        'body' => ['value' => "<p>Body for $title</p>", 'format' => 'basic_html'],
      ]);
      $article->save();
      $created_nids[] = (int) $article->id();
    }

    $form_object = \Drupal::entityTypeManager()
      ->getFormObject('node', 'default')
      ->setEntity($issue);
    $form = \Drupal::formBuilder()->getForm($form_object);
    if (isset($form['df_homepage_hero']) && ($form['df_homepage_hero']['#access'] ?? TRUE) !== FALSE) {
      $fail('web_editor_issue_form_hides_hero', 'Homepage hero video group must be hidden for web_editor.');
    }
    if (!isset($form['field_hero_heading'])) {
      $fail('web_editor_issue_form_hides_hero', 'Web editor must see field_hero_heading on the issue form.');
    }
    foreach (['field_hero_video', 'field_hero_poster'] as $hero_field) {
      if (isset($form[$hero_field])) {
        $fail('web_editor_issue_form_hides_hero', "Top-level $hero_field must not appear for web_editor.");
      }
    }
    $pass('web_editor_issue_form_hides_hero');

    /** @var IssuePublisher $publisher */
    $publisher = \Drupal::service('df_editor.issue_publisher');
    $published = $publisher->publishIssueWithArticles($issue);
    $account_switcher->switchBack();

    if (count($published) !== 3) {
      $fail('publish_three_articles', 'Expected 3 published articles.');
    }
    $pass('publish_three_articles');

    drupal_flush_all_caches();

    $issue = $storage->load($issue->id());
    if (!$issue instanceof NodeInterface || !$issue->isPublished()) {
      $fail('issue_is_latest', 'Issue must be published.');
    }
    if ((int) df_setup_get_latest_issue_nid() !== (int) $issue->id()) {
      $fail('issue_is_latest', 'Winter 2027 must be the latest published issue.');
    }
    $pass('issue_is_latest');

    $issue_path = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $issue->id());
    $issue_url = $issue->toUrl('canonical', ['absolute' => FALSE])->toString();
    $nav_label = df_setup_format_issue_nav_label($issue);
    $header_links = df_setup_get_header_issue_links(4);
    if ($header_links === [] || !str_contains($header_links[0]['title'], '2027') || !str_contains($header_links[0]['title'], 'Winter')) {
      $fail('header_issues_nav_position', 'Newest issue must be first in Issues nav (Winter 2027).');
    }
    if (isset($header_links[1]) && str_contains($header_links[1]['title'], '2027')) {
      $fail('header_issues_nav_position', 'Second nav slot must not be another 2027 issue.');
    }
    if ($header_links[0]['url'] !== $issue_url && $header_links[0]['url'] !== $issue_path) {
      $fail('header_issues_nav_position', 'First Issues nav link must point to the new issue.');
    }
    $pass('header_issues_nav_position');

    /** @var \Drupal\df_editor\CurrentIssueNav $nav */
    $nav = \Drupal::service('df_editor.current_issue_nav');
    $primary = $nav->applyCurrentIssueUrl([
      ['title' => 'Current Issue', 'url' => '/browse-issues'],
    ]);
    if (!in_array($primary[0]['url'] ?? '', [$issue_url, $issue_path], TRUE)) {
      $fail('current_issue_nav_link', 'Current Issue menu link must resolve to the latest issue.');
    }
    $pass('current_issue_nav_link');

    $front = $kernel_html('/');
    if (!str_contains($front, $hero_heading)) {
      $fail('homepage_features_current_issue', 'Homepage hero heading missing.');
    }
    if (!str_contains($front, 'Explore the Latest Issue')) {
      $fail('homepage_features_current_issue', 'Homepage Explore band missing.');
    }
    foreach ($article_titles as $title) {
      if (!str_contains($front, $title)) {
        $fail('homepage_features_current_issue', "Homepage missing article: $title");
      }
    }
    if (!str_contains($front, $issue_path) && !str_contains($front, $issue_url)) {
      $fail('homepage_features_current_issue', 'Homepage See Full Issue must link to latest issue.');
    }
    $pass('homepage_features_current_issue');

    drupal_flush_all_caches();
    $browse = (string) $http->get($base . '/browse-issues')->getBody();
    if (!preg_match('#<figure[^>]*issue-cover[^>]*>[\s\S]*?</figure>#', $browse, $first_card)) {
      $fail(
        'browse_issues_first_with_cover',
        'Browse issues grid missing an issue cover card (len=' . strlen($browse) . ', markers=' . substr_count($browse, 'issue-cover') . ').',
      );
    }
    $card = $first_card[0];
    if (!str_contains($card, $issue_path) && !str_contains($card, $issue_url)) {
      $fail('browse_issues_first_with_cover', 'First browse-issues card must be the new Winter 2027 issue.');
    }
    if (!preg_match('#style="width:250px;height:auto"#', $card)) {
      $fail('browse_issues_first_with_cover', 'First browse-issues cover must use width:250px;height:auto.');
    }
    if (!str_contains($browse, $nav_label)) {
      $fail('browse_issues_first_with_cover', 'Browse issues page missing Winter 2027 label.');
    }
    $pass('browse_issues_first_with_cover');

    $landing = $kernel_html($issue_path);
    foreach ($article_titles as $title) {
      if (!str_contains($landing, $title)) {
        $fail('issue_landing_lists_articles', "Issue landing missing: $title");
      }
    }
    $pass('issue_landing_lists_articles');
  }
  finally {
    foreach ($created_nids as $nid) {
      $node = $storage->load($nid);
      if ($node instanceof NodeInterface) {
        $node->delete();
      }
    }
    drupal_flush_all_caches();
  }

  return $results;
}

if (!defined('DF_EDITOR_E2E_SKIP_AUTO_RUN')) {
  $results = df_migrate_web_editor_issue_e2e();
  foreach ($results as $check => $ok) {
    print ($ok ? 'PASS' : 'FAIL') . " $check\n";
  }
  print "OK: web editor issue E2E (" . count($results) . " checks).\n";
}
