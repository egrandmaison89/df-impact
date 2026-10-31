<?php

/**
 * @file
 * Verifies web editors can publish articles with custom archive excerpts.
 *
 * Usage: drush scr web/modules/custom/df_migrate/scripts/test_archive_editor_excerpt.php
 */

declare(strict_types=1);

use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;
use GuzzleHttp\Client;

$base = rtrim(getenv('DRUPAL_BASE_URL') ?: 'http://127.0.0.1:8888', '/');
$http = new Client(['http_errors' => FALSE, 'timeout' => 45]);
$category_path = '/category/immunotherapy';
$custom_excerpt = 'DF parity editor excerpt ' . bin2hex(random_bytes(4));

$user_storage = \Drupal::entityTypeManager()->getStorage('user');
$editors = $user_storage->loadByProperties(['name' => 'df_test_web_editor']);
$editor = reset($editors);
if (!$editor) {
  $editor = User::create([
    'name' => 'df_test_web_editor',
    'mail' => 'df_test_web_editor@example.com',
    'pass' => 'test',
    'status' => 1,
    'roles' => ['web_editor'],
  ]);
  $editor->save();
}

$tids = df_setup_topic_term_tids_for_category_slug('immunotherapy');
if ($tids === []) {
  throw new \RuntimeException('No immunotherapy topic terms found.');
}

$account_switcher = \Drupal::service('account_switcher');
$account_switcher->switchTo($editor);

$node = Node::create([
  'type' => 'article',
  'title' => 'DF Archive Editor Excerpt Test',
  'status' => 1,
  'moderation_state' => 'published',
  'body' => ['value' => '<p>Body for archive editor excerpt test.</p>', 'format' => 'basic_html'],
  'field_excerpt' => ['value' => $custom_excerpt, 'format' => 'plain_text'],
  'field_topics' => [['target_id' => $tids[0]]],
]);
$node->save();
$nid = (int) $node->id();
$account_switcher->switchBack();

drupal_flush_all_caches();

$request = \Symfony\Component\HttpFoundation\Request::create($category_path, 'GET');
$request->attributes->set('df_setup_wp_archive_path', $category_path);
\Drupal::service('request_stack')->push($request);
$build = views_embed_view('topic_archive', 'page_1', 'all');
\Drupal::service('request_stack')->pop();
if (!is_array($build)) {
  $node->delete();
  throw new \RuntimeException('topic_archive view did not render.');
}
$html = (string) \Drupal::service('renderer')->renderPlain($build);
if (!str_contains($html, $custom_excerpt)) {
  $node->delete();
  throw new \RuntimeException('Custom excerpt not found in category archive listing.');
}
$pos_excerpt = strpos($html, $custom_excerpt);
$pos_title = strpos($html, 'DF Archive Editor Excerpt Test');
if ($pos_title === FALSE || $pos_excerpt === FALSE || $pos_title > $pos_excerpt) {
  $node->delete();
  throw new \RuntimeException('Archive row must include title before excerpt text.');
}
$pos_frozen = strpos($html, 'Donaldson Charitable Trust targets multiple myeloma');
if ($pos_frozen !== FALSE && $pos_title > $pos_frozen) {
  $node->delete();
  throw new \RuntimeException('Editor-created article should list before frozen WP archive rows.');
}

$response = $http->get($base . $category_path);
if ($response->getStatusCode() !== 200) {
  $node->delete();
  throw new \RuntimeException('Archive HTTP ' . $response->getStatusCode());
}

$node->delete();
print "OK: web_editor article published, listed on {$category_path} with custom field_excerpt, then deleted (nid was {$nid}).\n";
