<?php

declare(strict_types=1);

namespace Drupal\df_secondary\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * RSS feeds aligned with WordPress site and category archive feeds.
 */
final class FeedController extends ControllerBase {

  private const ORIGIN = 'https://danafarberimpact.org';

  /**
   * Site-wide RSS (WordPress /feed and Drupal rss.xml parity).
   */
  public function siteFeed(): Response {
    $nids = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->condition('status', 1)
      ->condition('type', ['article', 'in_brief'], 'IN')
      ->sort('created', 'DESC')
      ->range(0, 10)
      ->accessCheck(FALSE)
      ->execute();
    $items = [];
    foreach (Node::loadMultiple($nids) as $node) {
      if ($node instanceof NodeInterface) {
        $items[] = $this->buildItemFromNode($node);
      }
    }
    return $this->xmlResponse($this->buildRss('Impact Magazine', self::ORIGIN . '/', $items));
  }

  /**
   * Category topic archive feed.
   */
  public function categoryFeed(string $slug): Response {
    $terms = $this->entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
      'vid' => 'topics',
      'name' => str_replace('-', ' ', $slug),
    ]);
    $term = reset($terms);
    if (!$term instanceof TermInterface) {
      $terms = $this->entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
        'vid' => 'topics',
      ]);
      foreach ($terms as $candidate) {
        if ($this->slugify($candidate->label()) === $slug) {
          $term = $candidate;
          break;
        }
      }
    }
    if (!$term instanceof TermInterface) {
      throw new NotFoundHttpException();
    }

    $nids = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->condition('status', 1)
      ->condition('type', 'article')
      ->condition('field_topics.target_id', $term->id())
      ->sort('created', 'DESC')
      ->range(0, 20)
      ->accessCheck(FALSE)
      ->execute();

    $channel_link = self::ORIGIN . '/category/' . $slug . '/';
    $title = $term->label() . ' Archives - Impact Magazine';
    $items = [];
    foreach (Node::loadMultiple($nids) as $node) {
      $items[] = $this->buildItemFromNode($node);
    }

    return $this->xmlResponse($this->buildRss($title, $channel_link, $items));
  }

  /**
   * Single node comments-style feed (WordPress story /feed).
   */
  public function nodeFeed(NodeInterface $node): Response {
    if (!$node->isPublished()) {
      throw new NotFoundHttpException();
    }
    $link = self::originUrlForNode($node);
    $items = [$this->buildItemFromNode($node)];
    return $this->xmlResponse($this->buildRss($node->label() . ' - Impact Magazine', $link, $items));
  }

  /**
   * @return array{title: string, link: string, guid: string, description: string, pubDate: string}
   */
  private function buildItemFromNode(NodeInterface $node): array {
    $link = self::originUrlForNode($node);
    $guid = $link;
    $description = '';
    if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
      $description = strip_tags((string) $node->get('body')->value);
      $description = mb_substr(trim($description), 0, 500);
    }
    elseif ($node->hasField('field_excerpt') && !$node->get('field_excerpt')->isEmpty()) {
      $description = strip_tags((string) $node->get('field_excerpt')->value);
    }
    return [
      'title' => $node->label(),
      'link' => $link,
      'guid' => $guid,
      'description' => htmlspecialchars($description, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
      'pubDate' => gmdate('D, d M Y H:i:s \G\M\T', (int) $node->getCreatedTime()),
    ];
  }

  /**
   * @param array<int, array{title: string, link: string, guid: string, description: string, pubDate: string}> $items
   */
  private function buildRss(string $title, string $link, array $items): string {
    $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $out .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
    $out .= '<channel>' . "\n";
    $out .= '<title>' . htmlspecialchars($title, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</title>' . "\n";
    $out .= '<link>' . htmlspecialchars($link, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</link>' . "\n";
    $out .= '<description>Impact Magazine</description>' . "\n";
    $out .= '<language>en-US</language>' . "\n";
    $out .= '<atom:link href="' . htmlspecialchars($link . 'feed/', ENT_XML1 | ENT_QUOTES, 'UTF-8') . '" rel="self" type="application/rss+xml" />' . "\n";
    foreach ($items as $item) {
      $out .= '<item>' . "\n";
      $out .= '<title>' . htmlspecialchars($item['title'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</title>' . "\n";
      $out .= '<link>' . htmlspecialchars($item['link'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</link>' . "\n";
      $out .= '<guid isPermaLink="true">' . htmlspecialchars($item['guid'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</guid>' . "\n";
      $out .= '<description>' . $item['description'] . '</description>' . "\n";
      $out .= '<pubDate>' . $item['pubDate'] . '</pubDate>' . "\n";
      $out .= '</item>' . "\n";
    }
    $out .= '</channel></rss>';
    return $out;
  }

  private function xmlResponse(string $xml): Response {
    return new Response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=utf-8']);
  }

  private static function originUrlForNode(NodeInterface $node): string {
    $alias = $node->toUrl()->toString();
    $url = self::ORIGIN . $alias;
    if (!str_ends_with($url, '/')) {
      $url .= '/';
    }
    return $url;
  }

  private function slugify(string $label): string {
    $slug = strtolower($label);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;
    return trim($slug, '-');
  }

}
