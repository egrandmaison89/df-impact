<?php

namespace Drupal\df_setup;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;

/**
 * Normalizes article body HTML: issue/byline deck, duplicates, legacy links.
 */
final class ArticleBodyCleaner {

  /**
   * WP issue landing paths often look like /spring-2026-issue/.
   */
  private const ISSUE_PATH_PATTERN = '#/(spring|summer|fall|winter)-(\d{4})-issue#i';

  /**
   * Dated WP posts: /2026/03/post-slug/.
   */
  private const DATED_POST_PATTERN = '#https?://(?:www\.)?danafarberimpact\.org/\d{4}/\d{2}/([^/]+)/?#i';

  /** @var int Matches string field max_length (byline, photo_credit). */
  private const STRING_FIELD_MAX = 255;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  /**
   * Cleans article body and returns structured metadata for fields.
   *
   * @param string|null $featuredImageUrl
   *   Public URL for featured image file (WP URL during migrate; Drupal absolute
   *   URL during backfill) used to drop a duplicate lead figure from body.
   *
   * @return array{html: string, byline: string, photo_credit: string}
   */
  public function cleanArticleHtml(string $html, ?string $featuredImageUrl = NULL): array {
    $byline = '';
    $photo_credit = '';

    $html = $this->removeIssueDeckParagraphs($html, $byline, $photo_credit);
    $html = $this->removeStandaloneBylineParagraphs($html);

    $stem = self::stemBasenameFromUrl($featuredImageUrl);
    if ($stem !== '') {
      [$html, $captionFromFigure] = $this->stripLeadFigureMatchingFeatured($html, $stem);
      if ($captionFromFigure !== '' && $photo_credit === '') {
        $photo_credit = $this->normalizePhotoCreditText($captionFromFigure);
      }
    }

    $html = $this->rewriteInternalLinks($html);

    $html = preg_replace("/^\s+/", '', $html) ?? $html;
    $html = preg_replace("/\s+$/", '', $html) ?? $html;

    return [
      'html' => $html,
      'byline' => self::truncateUtf8(trim($byline), self::STRING_FIELD_MAX),
      'photo_credit' => self::truncateUtf8(trim($photo_credit), self::STRING_FIELD_MAX),
    ];
  }

  /**
   * First lead <figure> whose image matches featured stem: remove, return caption.
   *
   * @return array{0: string, 1: string}
   *   HTML and figcaption text (may be empty).
   */
  private function stripLeadFigureMatchingFeatured(string $html, string $featuredStem): array {
    if ($html === '' || !str_contains($html, '<figure')) {
      return [$html, ''];
    }

    $doc = new \DOMDocument();
    libxml_use_internal_errors(TRUE);
    $wrapped = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="df-root">' . $html . '</div></body></html>';
    $doc->loadHTML($wrapped);
    libxml_clear_errors();

    $xpath = new \DOMXPath($doc);
    $root = $doc->getElementById('df-root');
    if (!$root) {
      return [$html, ''];
    }

    $figures = $xpath->query('(.//figure)[1]', $root);
    if (!$figures || $figures->length === 0) {
      return [$html, ''];
    }

    /** @var \DOMElement $figure */
    $figure = $figures->item(0);
    $img = $xpath->query('.//img', $figure)->item(0);
    if (!$img instanceof \DOMElement) {
      return [$html, ''];
    }

    $src = $img->getAttribute('src') ?: $img->getAttribute('data-src');
    $src = html_entity_decode($src, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if ($src === '') {
      return [$html, ''];
    }

    $imgPath = parse_url($src, PHP_URL_PATH);
    $basename = is_string($imgPath) && $imgPath !== '' ? basename($imgPath) : basename($src);
    $imgStem = self::stemBasename($basename);

    if ($imgStem === '' || strcasecmp($imgStem, $featuredStem) !== 0) {
      return [$html, ''];
    }

    $caption = '';
    $cap = $xpath->query('.//figcaption', $figure)->item(0);
    if ($cap instanceof \DOMElement) {
      $caption = trim($cap->textContent);
    }

    $figure->parentNode?->removeChild($figure);

    $root = $doc->getElementById('df-root');
    if (!$root) {
      return [$html, ''];
    }
    $out = '';
    foreach ($root->childNodes as $child) {
      $out .= $doc->saveHTML($child);
    }

    return [$out, $caption];
  }

  /**
   * Removes issue summary paragraphs (WP + Drupal issue links).
   */
  private function removeIssueDeckParagraphs(string $html, string &$byline, string &$photo_credit): string {
    // Broad paragraph: first <a> is an "issue" link, optional <br> By / Photography lines.
    $pattern = '#<p\b[^>]*>\s*'
      . '<a\s+[^>]*\bhref=["\']([^"\']+)["\'][^>]*>([^<]*)</a>'
      . '(?:\s*<br\s*/?>\s*((?:By|Written by)\s+[^<]+?))?'
      . '(?:\s*<br\s*/?>\s*((?:Photograph(?:y|s)?|Photos?)\s+by\s*[^<]+?))?'
      . '\s*</p>#is';

    $limit = 8;
    while ($limit-- > 0) {
      if (!preg_match($pattern, $html, $m, PREG_OFFSET_CAPTURE)) {
        break;
      }
      $href = $m[1][0];
      $anchorHtml = $m[2][0];
      if (!$this->isIssueSummaryDeckHref($href, $anchorHtml)) {
        break;
      }

      if (!empty($m[3][0])) {
        $line = trim(html_entity_decode(strip_tags($m[3][0]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $line = preg_replace('/^Written by\s+/i', '', $line);
        if ($line !== '' && $byline === '') {
          $byline = ArticleCreditLabels::stripByline($line);
        }
      }
      if (!empty($m[4][0])) {
        $line = trim(html_entity_decode(strip_tags($m[4][0]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($line !== '' && $photo_credit === '') {
          $photo_credit = ArticleCreditLabels::stripPhotoCredit($line);
        }
      }

      $html = preg_replace($pattern, '', $html, 1) ?? $html;
    }

    return $html;
  }

  /**
   * TRUE when the link is the redundant “Spring 2026” issue summary (not a story URL).
   */
  private function isIssueSummaryDeckHref(string $href, string $anchorInnerHtml = ''): bool {
    $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($href === '') {
      return FALSE;
    }

    $path = parse_url($href, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
      $path = $href;
    }
    $path = '/' . ltrim($path, '/');

    if (preg_match('#^/issues/[a-z-]+-\d{4}(/|$)#i', $path)) {
      return TRUE;
    }

    if (preg_match(self::ISSUE_PATH_PATTERN, $path)) {
      return TRUE;
    }

    if (preg_match('#^/node/\d+#i', $path)) {
      if (preg_match('#^/node/(\d+)$#', $path, $mm)) {
        $nid = (int) $mm[1];
        $node = $this->entityTypeManager->getStorage('node')->load($nid);
        if ($node instanceof NodeInterface && $node->bundle() === 'issue') {
          return TRUE;
        }
      }
      return FALSE;
    }

    if (str_contains($href, 'danafarberimpact.org')) {
      if (preg_match(self::ISSUE_PATH_PATTERN, (string) parse_url($href, PHP_URL_PATH))) {
        return TRUE;
      }
      $wpPath = (string) parse_url($href, PHP_URL_PATH);
      if ($wpPath !== '' && preg_match('/issue/i', $wpPath)
          && !preg_match('#/\d{4}/\d{2}/#', $wpPath)) {
        return TRUE;
      }
    }

    $text = trim(html_entity_decode(strip_tags($anchorInnerHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if (preg_match('/^(Spring|Summer|Fall|Winter|Late Fall)\s+\d{4}\s*$/i', $text)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Strips standalone By / Photography lines (after deck removal).
   */
  private function removeStandaloneBylineParagraphs(string $html): string {
    $patterns = [
      '#<p[^>]*>\s*(?:<strong>)?\s*By\s+[^<]+?(?:</strong>)?\s*</p>#iu',
      '#<p[^>]*>\s*(?:<strong>)?\s*Written by\s+[^<]+?(?:</strong>)?\s*</p>#iu',
      '#<p[^>]*>\s*Photograph(?:y|s)?\s+by\s+[^<]*?</p>#i',
      '#<p[^>]*>\s*Photos?\s+by\s+[^<]*?</p>#i',
    ];
    foreach ($patterns as $p) {
      $html = preg_replace($p, '', $html) ?? $html;
    }
    return $html;
  }

  /**
   * Rewrites WordPress absolute URLs in anchors to local paths.
   */
  private function rewriteInternalLinks(string $html): string {
    if ($html === '' || !str_contains($html, 'danafarberimpact.org')) {
      return $html;
    }

    $doc = new \DOMDocument();
    libxml_use_internal_errors(TRUE);
    $wrapped = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="df-root">' . $html . '</div></body></html>';
    $doc->loadHTML($wrapped);
    libxml_clear_errors();

    $xpath = new \DOMXPath($doc);
    foreach ($xpath->query('//div[@id="df-root"]//a[@href]') as $link) {
      if (!$link instanceof \DOMElement) {
        continue;
      }
      $href = html_entity_decode($link->getAttribute('href'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
      $new = $this->mapExternalUrlToLocal($href);
      if ($new !== NULL) {
        $link->setAttribute('href', $new);
      }
    }

    $root = $doc->getElementById('df-root');
    if (!$root) {
      return $html;
    }
    $out = '';
    foreach ($root->childNodes as $child) {
      $out .= $doc->saveHTML($child);
    }
    return $out;
  }

  /**
   * Maps a legacy WordPress URL to a relative Drupal path when possible.
   */
  public function mapExternalUrlToLocal(string $href): ?string {
    $href = trim($href);
    if ($href === '' || !str_contains($href, 'danafarberimpact.org')) {
      return NULL;
    }

    $path = parse_url($href, PHP_URL_PATH);
    if (!is_string($path)) {
      return NULL;
    }

    if (preg_match(self::ISSUE_PATH_PATTERN, $path, $m)) {
      $season = strtolower($m[1]);
      $year = (int) $m[2];
      $nid = $this->findIssueNid($season, $year);
      if ($nid) {
        $node = $this->entityTypeManager->getStorage('node')->load($nid);
        if ($node instanceof NodeInterface) {
          return $node->toUrl('canonical', ['absolute' => FALSE])->toString();
        }
      }
      return '/issues/' . $season . '-' . $year;
    }

    if (preg_match(self::DATED_POST_PATTERN, $href, $m)) {
      return '/stories/' . $m[1];
    }

    return NULL;
  }

  private function findIssueNid(string $season, int $year): ?int {
    $ids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'issue')
      ->condition('status', 1)
      ->condition('field_season', $season)
      ->condition('field_year', $year)
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();
    if (!$ids) {
      return NULL;
    }
    return (int) reset($ids);
  }

  private function getImageUriFromMedia(MediaInterface $media): string {
    if (!$media->hasField('field_media_image') || $media->get('field_media_image')->isEmpty()) {
      return '';
    }
    /** @var \Drupal\file\FileInterface|null $file */
    $file = $media->get('field_media_image')->entity;
    return $file instanceof FileInterface ? $file->getFileUri() : '';
  }

  public function getFeaturedAbsoluteUrl(NodeInterface $node): ?string {
    if ($node->bundle() !== 'article' || !$node->hasField('field_featured_image') || $node->get('field_featured_image')->isEmpty()) {
      return NULL;
    }
    $media = $node->get('field_featured_image')->entity;
    if (!$media instanceof MediaInterface) {
      return NULL;
    }
    $uri = $this->getImageUriFromMedia($media);
    if ($uri === '') {
      return NULL;
    }
    return $this->fileUrlGenerator->generateAbsoluteString($uri);
  }

  /**
   * Normalizes figcaption / credit lines for field_photo_credit.
   */
  private function normalizePhotoCreditText(string $text): string {
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    return self::truncateUtf8(ArticleCreditLabels::stripPhotoCredit($text), self::STRING_FIELD_MAX);
  }

  /**
   * Truncates for string fields (max_length 255 in schema).
   */
  private static function truncateUtf8(string $text, int $max): string {
    if ($max <= 0 || $text === '') {
      return $text;
    }
    if (mb_strlen($text) <= $max) {
      return $text;
    }
    return mb_substr($text, 0, $max);
  }

  public static function stemBasenameFromUrl(?string $url): string {
    if ($url === NULL || $url === '') {
      return '';
    }
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $path = parse_url($url, PHP_URL_PATH);
    $base = is_string($path) && $path !== '' ? basename($path) : basename($url);
    return self::stemBasename($base);
  }

  public static function stemBasename(string $basename): string {
    $basename = preg_replace('/\?.*$/', '', $basename) ?? $basename;
    return strtolower(preg_replace('/-\d+x\d+(?=\.[^.]+$)/', '', $basename) ?? $basename);
  }

}
