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

  /** @var int Matches field_photo_caption max_length. */
  private const CAPTION_FIELD_MAX = 500;

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
   * @return array{html: string, byline: string, photo_credit: string, photo_caption: string}
   */
  public function cleanArticleHtml(string $html, ?string $featuredImageUrl = NULL): array {
    $byline = '';
    $photo_credit = '';
    $photo_caption = '';

    $html = $this->removeIssueDeckParagraphs($html, $byline, $photo_credit);
    $html = $this->removeStandaloneBylineParagraphs($html);

    $stem = self::stemBasenameFromUrl($featuredImageUrl);
    if ($stem !== '') {
      [$html, $captionFromFigure] = $this->stripLeadFigureMatchingFeatured($html, $stem);
      if ($captionFromFigure !== '') {
        $photo_caption = $this->normalizePhotoCaptionText($captionFromFigure);
      }
    }

    $html = $this->rewriteInternalLinks($html);
    $html = $this->convertOembedsToIframes($html);

    $html = preg_replace("/^\s+/", '', $html) ?? $html;
    $html = preg_replace("/\s+$/", '', $html) ?? $html;

    return [
      'html' => $html,
      'byline' => self::truncateUtf8(trim($byline), self::STRING_FIELD_MAX),
      'photo_credit' => self::truncateUtf8(trim($photo_credit), self::STRING_FIELD_MAX),
      'photo_caption' => self::truncateUtf8(trim($photo_caption), self::CAPTION_FIELD_MAX),
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
   * Normalizes figcaption text for field_photo_caption.
   */
  private function normalizePhotoCaptionText(string $text): string {
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    return self::truncateUtf8($text, self::CAPTION_FIELD_MAX);
  }

  /**
   * Normalizes photographer credit lines for field_photo_credit.
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

  /**
   * Converts WP oEmbed placeholders (video/social) to responsive iframes.
   */
  public function normalizeOembedHtml(string $html): string {
    return $this->convertOembedsToIframes($html);
  }

  /**
   * Counts bodies that still need oEmbed iframe conversion (for backfill reports).
   *
   * @return array{vimeo: int, youtube: int, twitter: int, instagram: int, plain_wrapper: int, total_markers: int}
   */
  public static function countOembedConversionMarkers(string $html): array {
    $html = $html ?? '';
    if ($html === '') {
      return [
        'vimeo' => 0,
        'youtube' => 0,
        'twitter' => 0,
        'instagram' => 0,
        'plain_wrapper' => 0,
        'total_markers' => 0,
      ];
    }
    $vimeo = preg_match_all('#vimeo\.com#i', $html) ?: 0;
    $youtube = preg_match_all('#(?:youtube\.com|youtu\.be)#i', $html) ?: 0;
    $twitter = preg_match_all('#(?:twitter\.com|x\.com)/[^/\s"\']+/status/\d+#i', $html) ?: 0;
    $instagram = preg_match_all('#instagram\.com/(?:p|reel|tv)/#i', $html) ?: 0;
    $plain_wrapper = 0;
    if (str_contains($html, 'wp-block-embed__wrapper')) {
      $doc = new \DOMDocument();
      libxml_use_internal_errors(TRUE);
      $wrapped = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="df-root">' . $html . '</div></body></html>';
      $doc->loadHTML($wrapped);
      libxml_clear_errors();
      $xpath = new \DOMXPath($doc);
      $wrappers = $xpath->query('//*[@id="df-root"]//div[contains(@class,"wp-block-embed__wrapper")]');
      if ($wrappers) {
        foreach ($wrappers as $wrapper) {
          if (!$wrapper instanceof \DOMElement) {
            continue;
          }
          if ($wrapper->getElementsByTagName('iframe')->length > 0) {
            continue;
          }
          $plain_wrapper++;
        }
      }
    }
    $total = $plain_wrapper + ($vimeo > 0 ? 1 : 0) + ($youtube > 0 ? 1 : 0);
    return [
      'vimeo' => (int) $vimeo,
      'youtube' => (int) $youtube,
      'twitter' => (int) $twitter,
      'instagram' => (int) $instagram,
      'plain_wrapper' => (int) $plain_wrapper,
      'total_markers' => (int) max($plain_wrapper, $total),
    ];
  }

  /**
   * Replace WP oEmbed placeholder URLs with responsive iframes.
   */
  private function convertOembedsToIframes(string $html): string {
    if ($html === '') {
      return $html;
    }
    $needles = ['wp-block-embed', 'vimeo', 'youtube', 'twitter', 'instagram', 'x.com', 'blockquote'];
    $hit = FALSE;
    foreach ($needles as $needle) {
      if (str_contains($html, $needle)) {
        $hit = TRUE;
        break;
      }
    }
    if (!$hit) {
      return $html;
    }

    $doc = new \DOMDocument();
    libxml_use_internal_errors(TRUE);
    $wrapped = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="df-root">' . $html . '</div></body></html>';
    $doc->loadHTML($wrapped);
    libxml_clear_errors();

    $xpath = new \DOMXPath($doc);
    $root = $doc->getElementById('df-root');
    if (!$root) {
      return $html;
    }

    $wrappers = $xpath->query('.//div[contains(@class,"wp-block-embed__wrapper")]', $root);
    if ($wrappers) {
      foreach ($wrappers as $wrapper) {
        if (!$wrapper instanceof \DOMElement) {
          continue;
        }
        if ($wrapper->getElementsByTagName('iframe')->length > 0) {
          continue;
        }
        $url = trim(html_entity_decode($wrapper->textContent ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $iframe = $this->buildEmbedShellForUrl($url, $doc);
        if ($iframe === NULL) {
          $iframe = $this->buildEmbedFromWrapperChildren($wrapper, $doc);
        }
        if ($iframe === NULL) {
          continue;
        }
        while ($wrapper->firstChild) {
          $wrapper->removeChild($wrapper->firstChild);
        }
        $wrapper->appendChild($iframe);
      }
    }

    $iframes = $xpath->query('.//iframe[@src]', $root);
    if ($iframes) {
      foreach ($iframes as $iframeEl) {
        if (!$iframeEl instanceof \DOMElement) {
          continue;
        }
        $src = html_entity_decode($iframeEl->getAttribute('src') ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = $this->normalizeOembedEmbedSrc($src);
        if ($normalized !== NULL && $normalized !== $src) {
          $iframeEl->setAttribute('src', $normalized);
        }
      }
    }

    $paragraphs = $xpath->query('.//p', $root);
    if ($paragraphs) {
      foreach ($paragraphs as $p) {
        if (!$p instanceof \DOMElement) {
          continue;
        }
        $text = trim(html_entity_decode($p->textContent ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($text === '' || $p->getElementsByTagName('a')->length > 0) {
          continue;
        }
        if (!preg_match('#^https?://#i', $text)) {
          continue;
        }
        $iframe = $this->buildEmbedShellForUrl($text, $doc);
        if ($iframe === NULL) {
          continue;
        }
        $figure = $doc->createElement('figure');
        $figure->setAttribute('class', 'wp-block-embed aligncenter is-type-video wp-block-embed-vimeo');
        $inner = $doc->createElement('div');
        $inner->setAttribute('class', 'wp-block-embed__wrapper');
        $inner->appendChild($iframe);
        $figure->appendChild($inner);
        $p->parentNode?->replaceChild($figure, $p);
      }
    }

    $out = '';
    foreach ($root->childNodes as $child) {
      $out .= $doc->saveHTML($child);
    }
    return $out !== '' ? $out : $html;
  }

  /**
   * Builds a responsive iframe shell for an oEmbed URL (video or social).
   */
  private function buildEmbedShellForUrl(string $url, \DOMDocument $doc): ?\DOMElement {
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($url === '') {
      return NULL;
    }

    $embedSrc = $this->normalizeOembedEmbedSrc($url);
    if ($embedSrc === NULL) {
      return NULL;
    }

    return $this->createResponsiveIframeShell($doc, $embedSrc, $this->oembedAspectPadding($embedSrc));
  }

  /**
   * Derives embed URL from Twitter blockquote / nested anchors inside a wrapper.
   */
  private function buildEmbedFromWrapperChildren(\DOMElement $wrapper, \DOMDocument $doc): ?\DOMElement {
    $xpath = new \DOMXPath($doc);
    $quotes = $xpath->query('.//blockquote[contains(@class,"twitter-tweet")]', $wrapper);
    if ($quotes && $quotes->length > 0) {
      foreach ($quotes as $quote) {
        if (!$quote instanceof \DOMElement) {
          continue;
        }
        $links = $quote->getElementsByTagName('a');
        for ($i = $links->length - 1; $i >= 0; $i--) {
          $href = $links->item($i)?->getAttribute('href') ?? '';
          if ($href !== '' && preg_match('#(?:twitter\.com|x\.com)/.+/status/\d+#i', $href)) {
            return $this->buildEmbedShellForUrl($href, $doc);
          }
        }
      }
    }
    $anchors = $wrapper->getElementsByTagName('a');
    for ($i = 0; $i < $anchors->length; $i++) {
      $href = $anchors->item($i)?->getAttribute('href') ?? '';
      if ($href === '') {
        continue;
      }
      $shell = $this->buildEmbedShellForUrl($href, $doc);
      if ($shell !== NULL) {
        return $shell;
      }
    }
    return NULL;
  }

  /**
   * @return \DOMElement|null
   *   Responsive padding wrapper containing iframe.
   */
  private function createResponsiveIframeShell(\DOMDocument $doc, string $embedSrc, string $padding): ?\DOMElement {
    if ($embedSrc === '') {
      return NULL;
    }
    $shell = $doc->createElement('div');
    $shell->setAttribute('style', $padding);

    $iframe = $doc->createElement('iframe');
    $iframe->setAttribute('src', $embedSrc);
    $iframe->setAttribute('style', 'position:absolute;top:0;left:0;width:100%;height:100%;');
    $iframe->setAttribute('frameborder', '0');
    $iframe->setAttribute('allow', 'autoplay; fullscreen; picture-in-picture; clipboard-write; encrypted-media; web-share');
    $iframe->setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
    $iframe->setAttribute('allowfullscreen', 'true');
    $iframe->setAttribute('title', 'Embedded content');
    if (str_contains($embedSrc, 'platform.twitter.com') || str_contains($embedSrc, 'instagram.com')) {
      $iframe->setAttribute('scrolling', 'no');
    }

    $shell->appendChild($iframe);
    return $shell;
  }

  private function oembedAspectPadding(string $embedSrc): string {
    if (str_contains($embedSrc, 'platform.twitter.com') || str_contains($embedSrc, 'instagram.com')) {
      return 'max-width:540px;margin:0 auto;';
    }
    return 'padding:56.25% 0 0 0;position:relative;';
  }

  /**
   * Normalizes watch/share URLs to player embed URLs (WP oEmbed parity).
   */
  private function normalizeOembedEmbedSrc(string $url): ?string {
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($url === '') {
      return NULL;
    }
    if (preg_match('#^https?://player\.vimeo\.com/video/\d+#i', $url)) {
      return $url;
    }
    if (preg_match('#vimeo\.com/(?:video/)?(\d+)(?:/([a-f0-9]+))?#i', $url, $m)) {
      $src = 'https://player.vimeo.com/video/' . $m[1];
      if (!empty($m[2])) {
        $src .= '?h=' . $m[2];
      }
      return $src;
    }
    if (preg_match('#youtube\.com/embed/([a-zA-Z0-9_-]+)#i', $url, $m)) {
      return 'https://www.youtube.com/embed/' . $m[1];
    }
    if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/)([a-zA-Z0-9_-]+)#i', $url, $m)) {
      return 'https://www.youtube.com/embed/' . $m[1];
    }
    if (preg_match('#(?:twitter\.com|x\.com)/(?:#!/)?([^/]+)/status(?:es)?/(\d+)#i', $url, $m)) {
      return 'https://platform.twitter.com/embed/Tweet.html?id=' . $m[2];
    }
    if (preg_match('#instagram\.com/(?:p|reel|tv)/([A-Za-z0-9_-]+)#i', $url, $m)) {
      return 'https://www.instagram.com/p/' . $m[1] . '/embed';
    }
    if (preg_match('#^https?://(?:www\.)?instagram\.com/.+/embed#i', $url)) {
      return $url;
    }
    return NULL;
  }

}
