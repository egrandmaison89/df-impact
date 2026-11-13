<?php

declare(strict_types=1);

namespace Drupal\Tests\df_editor\ExistingSite;

use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Editor HTML format and legacy Full HTML preservation on migrated articles.
 *
 * @group df_editor
 */
#[Group('df_editor')]
final class EditorHtmlFormatTest extends ExistingSiteBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    if (!\Drupal::moduleHandler()->moduleExists('df_editor')) {
      $this->markTestSkipped('df_editor must be enabled.');
    }
    if (!\Drupal::entityTypeManager()->getStorage('filter_format')->load('editor_html')) {
      $this->markTestSkipped('editor_html format must be installed (run updb or config import).');
    }
  }

  /**
   * check_markup on editor_html removes script tags.
   */
  public function testEditorHtmlFormatStripsScriptTag(): void {
    $dirty = '<p>Safe</p><script>alert(1)</script>';
    $filtered = (string) check_markup($dirty, 'editor_html');
    $this->assertStringNotContainsString('<script', $filtered);
    $this->assertStringContainsString('Safe', $filtered);
  }

  /**
   * Web editor can save migrated full_html articles without changing rendered body.
   */
  public function testWebEditorSavePreservesMigratedArticleRender(): void {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $nids = $storage->getQuery()
      ->condition('type', 'article')
      ->condition('status', 1)
      ->exists('body')
      ->sort('nid', 'ASC')
      ->range(0, 20)
      ->accessCheck(FALSE)
      ->execute();
    $candidates = [];
    foreach ($storage->loadMultiple($nids) as $node) {
      if ($node->get('body')->format === 'full_html' && !$node->get('body')->isEmpty()) {
        $candidates[] = $node;
      }
      if (count($candidates) >= 2) {
        break;
      }
    }
    if (count($candidates) < 2) {
      $this->markTestSkipped('Need at least two published migrated articles with full_html body.');
    }

    $editor = User::create([
      'name' => 'editor_html_save_' . $this->randomMachineName(4),
      'mail' => 'editor_html_save@example.com',
      'pass' => 'test-pass',
      'status' => 1,
      'roles' => ['web_editor'],
    ]);
    $editor->save();
    $this->markEntityForCleanup($editor);

    $base = rtrim(getenv('DRUPAL_BASE_URL') ?: 'http://127.0.0.1:8888', '/');

    foreach ($candidates as $article) {
      $body_field = $article->get('body');
      $render_before = (string) check_markup($body_field->value, $body_field->format);
      $body_snippet_before = $this->extractArticleBodyHtml($base . $article->toUrl()->toString());

      \Drupal::service('account_switcher')->switchTo($editor);
      $article->setTitle($article->label());
      $article->save();
      \Drupal::service('account_switcher')->switchBack();

      $article = Node::load($article->id());
      $this->assertSame('full_html', $article->get('body')->format);
      $render_after = (string) check_markup($article->get('body')->value, $article->get('body')->format);
      $this->assertSame($render_before, $render_after, 'Filtered body HTML changed after web editor save for node ' . $article->id());

      $body_snippet_after = $this->extractArticleBodyHtml($base . $article->toUrl()->toString());
      $this->assertSame($body_snippet_before, $body_snippet_after, 'Rendered article body region changed for node ' . $article->id());
    }
  }

  /**
   * Returns normalized HTML for the article body field on the canonical page.
   */
  private function extractArticleBodyHtml(string $url): string {
    $html = (string) file_get_contents($url);
    if (!preg_match('/<article[^>]*class="[^"]*node--view-mode-full[^"]*"[^>]*>.*?<div class="entry-content alignwide wp-block-post-content[^"]*">(.*?)<\/div>\s*<\/div>\s*<\/article>/s', $html, $matches)) {
      $this->fail('Could not locate article body on ' . $url);
    }
    return preg_replace('/\s+/', ' ', $matches[1]) ?? $matches[1];
  }

}
