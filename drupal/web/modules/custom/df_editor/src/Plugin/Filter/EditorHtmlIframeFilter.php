<?php

declare(strict_types=1);

namespace Drupal\df_editor\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\filter\Attribute\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;

/**
 * Allows iframes only from approved video embed hosts.
 */
#[Filter(
  id: 'editor_html_iframe',
  title: new TranslatableMarkup('Editor HTML: restrict iframe embed hosts'),
  type: FilterInterface::TYPE_HTML_RESTRICTOR,
  weight: -8,
)]
final class EditorHtmlIframeFilter extends FilterBase {

  /**
   * Host suffixes allowed in iframe src (host must equal or end with .suffix).
   */
  private const ALLOWED_HOST_SUFFIXES = [
    'player.vimeo.com',
    'youtube.com',
    'www.youtube.com',
    'youtube-nocookie.com',
    'www.youtube-nocookie.com',
  ];

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode): FilterProcessResult {
    if ($text === '' || $text === NULL) {
      return new FilterProcessResult('');
    }

    $dom = Html::load($text);
    if (!$dom || !$dom->documentElement) {
      return new FilterProcessResult($text);
    }

    $xpath = new \DOMXPath($dom);
    foreach ($xpath->query('//iframe') ?: [] as $iframe) {
      if (!$iframe instanceof \DOMElement) {
        continue;
      }
      $src = trim($iframe->getAttribute('src'));
      if ($src === '' || !$this->isAllowedIframeSrc($src)) {
        $iframe->parentNode?->removeChild($iframe);
      }
    }

    $body = $dom->getElementsByTagName('body')->item(0);
    $output = '';
    if ($body) {
      foreach ($body->childNodes as $child) {
        $output .= $dom->saveHTML($child);
      }
    }

    return new FilterProcessResult($output);
  }

  /**
   * Whether an iframe src URL is from an allowed host.
   */
  private function isAllowedIframeSrc(string $src): bool {
    $parts = parse_url($src);
    if (empty($parts['host'])) {
      return FALSE;
    }
    $host = strtolower($parts['host']);
    $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
    if (!in_array($scheme, ['http', 'https'], TRUE)) {
      return FALSE;
    }
    foreach (self::ALLOWED_HOST_SUFFIXES as $allowed) {
      if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
