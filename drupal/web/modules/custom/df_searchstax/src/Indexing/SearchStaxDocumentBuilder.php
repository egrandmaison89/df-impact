<?php

declare(strict_types=1);

namespace Drupal\df_searchstax\Indexing;

use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\node\NodeInterface;

/**
 * Builds Solr-style JSON documents for SearchStax /update from Impact nodes.
 *
 * Field names align with the Site Search app's Select API (see fl / result_card
 * metadata): title, body, story image, teaser/ribbon. Solr document id is the
 * canonical public URL so result cards get a link (custom url_* fields may be
 * dropped by SearchStax ingest).
 */
final class SearchStaxDocumentBuilder {

  /**
   * Bundles included in the external search index.
   */
  public const INDEXED_BUNDLES = ['article', 'issue', 'page', 'in_brief'];

  public function __construct(
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  /**
   * Solr document id: canonical URL (matches Site Search crawler docs; custom url_* fields may be dropped by ingest).
   */
  public function indexDocumentId(NodeInterface $node, string $publicBase): string {
    return $this->canonicalUrl($node, $publicBase);
  }

  /**
   * Builds one document, or NULL if the node should not be indexed.
   *
   * @return array<string, mixed>|null
   *   Payload keys match Solr dynamic / configured fields for app 2176.
   */
  public function build(NodeInterface $node, string $publicBase): ?array {
    if (!in_array($node->bundle(), self::INDEXED_BUNDLES, TRUE)) {
      return NULL;
    }
    if (!$node->isPublished()) {
      return NULL;
    }

    $url = $this->canonicalUrl($node, $publicBase);

    $title = $node->getTitle();
    $bodyText = $this->collectBodyText($node);
    $ribbon = $this->buildRibbonMeta($node);
    $imageUrl = $this->resolveThumbnailUrl($node);

    $doc = [
      'id' => $url,
      'tcngramm_X3b_en_title' => $title,
      'tm_X3b_en_body' => $bodyText !== '' ? [$bodyText] : [$title],
    ];

    if ($ribbon !== '') {
      $doc['tcngramm_X3b_en_field_story_teaser'] = $ribbon;
    }

    if ($imageUrl !== '') {
      $doc['ss_field_story_image'] = $imageUrl;
    }

    $created = (int) $node->getCreatedTime();
    if ($created > 0) {
      $doc['created_dt'] = gmdate('Y-m-d\TH:i:s\Z', $created);
    }

    $author = $this->resolveAuthor($node);
    if ($author !== '') {
      $doc['author_t'] = $author;
    }

    return $doc;
  }

  private function canonicalUrl(NodeInterface $node, string $publicBase): string {
    $publicBase = rtrim($publicBase, '/');
    $url = $node->toUrl('canonical', ['absolute' => TRUE])->toString();
    if ($publicBase !== '') {
      $path = $node->toUrl()->toString();
      if (str_starts_with($path, '/')) {
        $url = $publicBase . $path;
      }
    }
    return $url;
  }

  private function collectBodyText(NodeInterface $node): string {
    $parts = [];
    if ($node->hasField('field_subtitle') && !$node->get('field_subtitle')->isEmpty()) {
      $parts[] = strip_tags((string) $node->get('field_subtitle')->value);
    }
    if ($node->hasField('field_excerpt') && !$node->get('field_excerpt')->isEmpty()) {
      $parts[] = strip_tags((string) $node->get('field_excerpt')->value);
    }
    if ($node->hasField('body')) {
      foreach ($node->get('body') as $item) {
        if (isset($item->value) && $item->value !== '') {
          $parts[] = strip_tags((string) $item->value);
        }
      }
    }
    if ($node->hasField('field_description') && !$node->get('field_description')->isEmpty()) {
      foreach ($node->get('field_description') as $item) {
        if ($item->value !== NULL) {
          $parts[] = strip_tags((string) $item->value);
        }
      }
    }
    if ($node->hasField('field_summary') && !$node->get('field_summary')->isEmpty()) {
      foreach ($node->get('field_summary') as $item) {
        if ($item->value !== NULL) {
          $parts[] = strip_tags((string) $item->value);
        }
      }
    }

    $text = trim(implode("\n\n", array_filter($parts)));
    return $this->truncate($text, 500_000);
  }

  /**
   * Ribbon / teaser line: type, date, author for result cards.
   */
  private function buildRibbonMeta(NodeInterface $node): string {
    $labels = [
             'article' => 'Story',
             'issue' => 'Issue',
             'page' => 'Page',
             'in_brief' => 'In Brief',
    ];
    $typeLabel = $labels[$node->bundle()] ?? $node->bundle();
    $created = $node->getCreatedTime();
    $dateStr = $created > 0 ? gmdate('F j, Y', (int) $created) : '';
    $by = $this->resolveAuthor($node);
    $bits = array_filter([$typeLabel, $dateStr, $by]);
    return implode(' · ', $bits);
  }

  private function resolveAuthor(NodeInterface $node): string {
    if ($node->bundle() !== 'article') {
      return '';
    }
    if (!$node->hasField('field_byline') || $node->get('field_byline')->isEmpty()) {
      return '';
    }
    return trim(strip_tags((string) $node->get('field_byline')->value));
  }

  private function resolveThumbnailUrl(NodeInterface $node): string {
    $fields = match ($node->bundle()) {
      'article' => ['field_featured_image'],
      'issue' => ['field_cover_image', 'field_banner_image'],
      'page' => ['field_banner_image'],
      'in_brief' => ['field_image'],
      default => [],
    };

    foreach ($fields as $fieldName) {
      if (!$node->hasField($fieldName) || $node->get($fieldName)->isEmpty()) {
        continue;
      }
      $url = $this->urlFromImageField($node->get($fieldName));
      if ($url !== '') {
        return $url;
      }
    }
    return '';
  }

  /**
   * Resolves absolute URL from an image or media reference field item list.
   */
  private function urlFromImageField(FieldItemListInterface $field): string {
    foreach ($field as $item) {
      $target = $item->entity;
      if ($target === NULL) {
        continue;
      }
      if ($target->getEntityTypeId() === 'media') {
        foreach (['field_media_image', 'field_media_photo'] as $fname) {
          if (!$target->hasField($fname) || $target->get($fname)->isEmpty()) {
            continue;
          }
          $file = $target->get($fname)->entity;
          if ($file !== NULL && method_exists($file, 'getFileUri') && $file->getFileUri()) {
            return $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
          }
        }
        continue;
      }
      if (method_exists($target, 'getFileUri')) {
        $uri = $target->getFileUri();
        if ($uri) {
          return $this->fileUrlGenerator->generateAbsoluteString($uri);
        }
      }
    }
    return '';
  }

  private function truncate(string $text, int $max): string {
    if (strlen($text) <= $max) {
      return $text;
    }
    return substr($text, 0, $max);
  }

}
