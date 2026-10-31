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
 * Strips script tags and event-handler attributes from editor HTML.
 */
#[Filter(
  id: 'editor_html_sanitize',
  title: new TranslatableMarkup('Editor HTML: strip scripts and event handlers'),
  type: FilterInterface::TYPE_HTML_RESTRICTOR,
  weight: -9,
)]
final class EditorHtmlSanitizeFilter extends FilterBase {

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
    foreach ($xpath->query('//script') ?: [] as $script) {
      $script->parentNode?->removeChild($script);
    }

    foreach ($xpath->query('//*') ?: [] as $element) {
      if (!$element instanceof \DOMElement) {
        continue;
      }
      $remove = [];
      foreach ($element->attributes ?? [] as $attribute) {
        $name = strtolower($attribute->name);
        if (str_starts_with($name, 'on')) {
          $remove[] = $attribute->name;
        }
      }
      foreach ($remove as $name) {
        $element->removeAttribute($name);
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

}
