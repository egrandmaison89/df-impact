<?php

declare(strict_types=1);

namespace Drupal\df_searchstax\Indexing;

use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Builds Solr-style JSON documents for SearchStax /update from Impact nodes.
 *
 * Field names align with the Site Search app's Select API (see fl / result_card
 * metadata). Search is article-first; server-side facets use issue_title_s and
 * article_category_s so filters apply to the full SearchStax result set.
 */
final class SearchStaxDocumentBuilder {

  /**
   * Bundles included in the external article search index.
   */
  public const INDEXED_BUNDLES = ['article'];

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
    $typeLabel = $this->facetContentTypeLabel($node->bundle());

    $doc = [
      'id' => $url,
      'index_source_s' => 'drupal',
      'search_scope_s' => 'articles',
      'tcngramm_X3b_en_title' => $title,
      'tm_X3b_en_body' => $bodyText !== '' ? [$bodyText] : [$title],
      'content_type_s' => $typeLabel,
    ];

    $issue = $this->issueFacetValues($node, $publicBase);
    if ($issue !== []) {
      $doc += $issue;
    }

    $topics = $this->taxonomyTermLabels($node, 'field_topics');
    if ($topics !== []) {
      $doc['topic_ss'] = $topics;
    }
    $articleCategories = $this->taxonomyTermLabels($node, 'field_content_type');
    if ($articleCategories !== []) {
      $doc['article_category_s'] = $articleCategories[0];
    }
    $cancerTypes = $this->taxonomyTermLabels($node, 'field_cancer_types');
    if ($cancerTypes !== []) {
      $doc['cancer_type_ss'] = $cancerTypes;
    }

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

  /**
   * Single-value facet for the indexed bundle.
   */
  private function facetContentTypeLabel(string $bundle): string {
    return match ($bundle) {
      'article' => 'Article',
      default => $bundle,
    };
  }

  /**
   * @return array<string, string>
   */
  private function issueFacetValues(NodeInterface $node, string $publicBase): array {
    if (!$node->hasField('field_issue') || $node->get('field_issue')->isEmpty()) {
      return [];
    }
    $issue = $node->get('field_issue')->entity;
    if (!$issue instanceof NodeInterface || !$issue->isPublished()) {
      return [];
    }
    $title = trim($issue->label());
    if ($title === '') {
      return [];
    }

    $out = [
      'issue_id_s' => (string) $issue->id(),
      'issue_title_s' => $title,
    ];
    $url = $this->canonicalUrl($issue, $publicBase);
    if ($url !== '') {
      $out['issue_url_s'] = $url;
      $slug = trim((string) parse_url($url, PHP_URL_PATH), '/');
      if ($slug !== '') {
        $out['issue_slug_s'] = basename($slug);
      }
    }
    return $out;
  }

  /**
   * @return list<string>
   */
  private function taxonomyTermLabels(NodeInterface $node, string $fieldName): array {
    if (!$node->hasField($fieldName) || $node->get($fieldName)->isEmpty()) {
      return [];
    }
    $labels = [];
    foreach ($node->get($fieldName)->referencedEntities() as $entity) {
      if ($entity instanceof TermInterface) {
        $label = trim($entity->label());
        if ($label !== '') {
          $labels[] = $label;
        }
      }
    }
    return array_values(array_unique($labels));
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
   * Ribbon / teaser line: type, issue, date, author for result cards.
   */
  private function buildRibbonMeta(NodeInterface $node): string {
    $typeLabel = $this->facetContentTypeLabel($node->bundle());
    $issueTitle = $this->issueDisplayTitle($node);
    $articleCategories = $this->taxonomyTermLabels($node, 'field_content_type');
    $category = $articleCategories[0] ?? '';
    $created = $node->getCreatedTime();
    $dateStr = $created > 0 ? gmdate('F j, Y', (int) $created) : '';
    $by = $this->resolveAuthor($node);
    $bits = array_filter([$typeLabel, $issueTitle, $category, $dateStr, $by]);
    return implode(' · ', $bits) . $this->ribbonFacetComment($node);
  }

  private function issueDisplayTitle(NodeInterface $node): string {
    if (!$node->hasField('field_issue') || $node->get('field_issue')->isEmpty()) {
      return '';
    }
    $issue = $node->get('field_issue')->entity;
    if (!$issue instanceof NodeInterface || !$issue->isPublished()) {
      return '';
    }
    return trim($issue->label());
  }

  /**
   * Hidden machine-readable facet payload (stripped client-side for display).
   */
  private function ribbonFacetComment(NodeInterface $node): string {
    $parts = ['t:' . rawurlencode($this->facetContentTypeLabel($node->bundle()))];
    $issueTitle = $this->issueDisplayTitle($node);
    if ($issueTitle !== '') {
      $parts[] = 'is:' . rawurlencode($issueTitle);
    }
    foreach ($this->taxonomyTermLabels($node, 'field_content_type') as $cat) {
      $parts[] = 'cf:' . rawurlencode($cat);
    }
    foreach ($this->taxonomyTermLabels($node, 'field_topics') as $topic) {
      $parts[] = 'tp:' . rawurlencode($topic);
    }
    foreach ($this->taxonomyTermLabels($node, 'field_cancer_types') as $cancerType) {
      $parts[] = 'c:' . rawurlencode($cancerType);
    }
    return ' <!--df:' . implode('|', $parts) . '-->';
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
