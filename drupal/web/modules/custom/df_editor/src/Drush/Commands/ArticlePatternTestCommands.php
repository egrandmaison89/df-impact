<?php

declare(strict_types=1);

namespace Drupal\df_editor\Drush\Commands;

use Drupal\node\Entity\Node;
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
    $base = rtrim(getenv('DRUPAL_BASE_URL') ?: 'http://127.0.0.1:8888', '/');
    $issue_nid = function_exists('df_setup_get_latest_issue_nid') ? df_setup_get_latest_issue_nid() : NULL;

    $node = Node::create([
      'type' => 'article',
      'title' => 'DF Editor Pattern Test ' . date('Y-m-d H:i:s'),
      'status' => 1,
      'moderation_state' => 'published',
      'body' => ['value' => self::SAMPLE_BODY, 'format' => 'full_html'],
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
    if ($missing !== []) {
      $node->delete();
      throw new \RuntimeException('Missing pattern markup on rendered page: ' . implode(', ', $missing));
    }

    $node->delete();
    $this->logger()->success('df-editor:test-article-patterns passed for ' . $url);
  }

}
