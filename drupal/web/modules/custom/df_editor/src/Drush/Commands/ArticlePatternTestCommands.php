<?php

declare(strict_types=1);

namespace Drupal\df_editor\Drush\Commands;

use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Verifies editor-created article HTML renders WP-style patterns.
 */
final class ArticlePatternTestCommands extends DrushCommands {

  private const SAMPLE_BODY = <<<'HTML'
<p class="wp-block-paragraph">Editor pattern test lead paragraph.</p>
<figure class="wp-block-pullquote"><blockquote><p>Pull quote text for parity.</p></blockquote></figure>
<figure class="wp-block-image alignwide size-large"><img src="/sites/default/files/wp-images/dfci.jpg" alt="Test caption image" width="725" height="520"><figcaption class="wp-element-caption">Image caption under figure.</figcaption></figure>
<div class="wp-block-embed is-type-video is-provider-vimeo wp-block-embed-vimeo"><div class="wp-block-embed__wrapper"><iframe src="https://player.vimeo.com/video/76979871" width="640" height="360" frameborder="0" allowfullscreen></iframe></div></div>
<div class="wp-block-buttons"><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://www.jimmyfund.org">Donate</a></div></div>
<div style="height:32px" aria-hidden="true" class="wp-block-spacer"></div>
HTML;

  #[CLI\Command(name: 'df-editor:test-article-patterns')]
  #[CLI\Usage(name: 'df-editor:test-article-patterns', description: 'Create a test article, verify pattern markup on canonical page, delete node')]
  public function testArticlePatterns(): void {
    if (!\Drupal::entityTypeManager()->getStorage('filter_format')->load('editor_html')) {
      throw new \RuntimeException('editor_html text format is not installed.');
    }

    $this->assertScriptStrippedByFormat();
    $this->assertPatternsRender('editor_html');
    $this->assertMigratedArticlesSurviveWebEditorSave();
    $this->logger()->success('df-editor:test-article-patterns passed (editor_html patterns, script strip, migrated save).');
  }

  /**
   * Ensures editor_html filter pipeline removes script tags.
   */
  private function assertScriptStrippedByFormat(): void {
    $dirty = self::SAMPLE_BODY . '<script>document.cookie="x"</script>';
    $filtered = (string) check_markup($dirty, 'editor_html');
    if (str_contains($filtered, '<script')) {
      throw new \RuntimeException('editor_html must strip script tags.');
    }
  }

  /**
   * Creates a temporary published article and checks the canonical page.
   */
  private function assertPatternsRender(string $format): void {
    $base = rtrim(getenv('DRUPAL_BASE_URL') ?: 'http://127.0.0.1:8888', '/');
    $issue_nid = function_exists('df_setup_get_latest_issue_nid') ? df_setup_get_latest_issue_nid() : NULL;

    $node = Node::create([
      'type' => 'article',
      'title' => 'DF Editor Pattern Test ' . date('Y-m-d H:i:s'),
      'status' => 1,
      'moderation_state' => 'published',
      'body' => ['value' => self::SAMPLE_BODY, 'format' => $format],
    ]);
    if ($issue_nid) {
      $node->set('field_issue', ['target_id' => $issue_nid]);
    }
    $node->save();

    $url = $base . $node->toUrl()->toString();
    $html = (string) file_get_contents($url);
    $needles = [
      'wp-block-pullquote',
      'wp-element-caption',
      'player.vimeo.com',
      'wp-block-button__link',
      'wp-block-spacer',
    ];
    $missing = array_filter($needles, static fn (string $n): bool => !str_contains($html, $n));
    $node->delete();
    if ($missing !== []) {
      throw new \RuntimeException('Missing pattern markup on rendered page: ' . implode(', ', $missing));
    }
    $this->logger()->notice('Pattern render OK for format ' . $format . ' at ' . $url);
  }

  /**
   * Web editor save must not change rendered HTML on two migrated full_html articles.
   */
  private function assertMigratedArticlesSurviveWebEditorSave(): void {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $nids = $storage->getQuery()
      ->condition('type', 'article')
      ->condition('status', 1)
      ->exists('body')
      ->sort('nid', 'ASC')
      ->range(0, 30)
      ->accessCheck(FALSE)
      ->execute();

    $articles = [];
    foreach ($storage->loadMultiple($nids) as $node) {
      if ($node->get('body')->format === 'full_html' && !$node->get('body')->isEmpty()) {
        $articles[] = $node;
      }
      if (count($articles) >= 2) {
        break;
      }
    }
    if (count($articles) < 2) {
      $this->logger()->warning('Skipped migrated-article save check: fewer than two published full_html articles.');
      return;
    }

    $web_editors = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['name' => 'df_test_web_editor']);
    $web_editor = reset($web_editors);
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

    $base = rtrim(getenv('DRUPAL_BASE_URL') ?: 'http://127.0.0.1:8888', '/');
    $switcher = \Drupal::service('account_switcher');
    foreach ($articles as $article) {
      $url = $base . $article->toUrl()->toString();
      $body_before = $this->extractArticleBodyHtml($url);
      $render_before = (string) check_markup($article->get('body')->value, 'full_html');

      $switcher->switchTo($web_editor);
      $article->setTitle($article->label());
      $article->save();
      $switcher->switchBack();

      $reloaded = Node::load($article->id());
      if ($reloaded->get('body')->format !== 'full_html') {
        throw new \RuntimeException('Migrated article ' . $article->id() . ' body format changed from full_html.');
      }
      $render_after = (string) check_markup($reloaded->get('body')->value, 'full_html');
      if ($render_before !== $render_after) {
        throw new \RuntimeException('Filtered body HTML changed after web editor save for node ' . $article->id());
      }
      $body_after = $this->extractArticleBodyHtml($url);
      if ($body_before !== $body_after) {
        throw new \RuntimeException('Rendered article body changed after save for node ' . $article->id());
      }
    }
    $this->logger()->notice('Migrated full_html save parity OK for ' . count($articles) . ' articles.');
  }

  /**
   * Normalizes the article body field HTML from a canonical page response.
   */
  private function extractArticleBodyHtml(string $url): string {
    $html = (string) file_get_contents($url);
    if (!preg_match('/<article[^>]*class="[^"]*node--view-mode-full[^"]*"[^>]*>.*?<div class="entry-content alignwide wp-block-post-content[^"]*">(.*?)<\/div>\s*<\/div>\s*<\/article>/s', $html, $matches)) {
      throw new \RuntimeException('Could not locate article body on ' . $url);
    }
    return preg_replace('/\s+/', ' ', $matches[1]) ?? $matches[1];
  }

}
