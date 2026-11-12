<?php

declare(strict_types=1);

namespace Drupal\df_editor;

/**
 * Stored CKEditor uploads become alignable, captioned images.
 *
 * Drupal's image plugin saves an img (or drupal-media) with data-align and
 * data-caption. filter_align and filter_caption turn that into a figure on
 * output. New uploads arrive as a bare img inside a paragraph, so this adds
 * the attributes the public theme renders as wp-block-image markup.
 *
 * Migrated Gutenberg bodies (<!-- wp:) are left unchanged.
 */
final class EditorBodyImages {

  public static function normalize(string $html): string {
    if ($html === '' || str_contains($html, '<!-- wp:')) {
      return $html;
    }
    if (!str_contains($html, '<img') && !str_contains($html, '<drupal-media')) {
      return $html;
    }

    $previous = libxml_use_internal_errors(TRUE);
    $dom = new \DOMDocument();
    $wrapped = '<div id="df-editor-body">' . $html . '</div>';
    $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
      return $html;
    }

    $xpath = new \DOMXPath($dom);
    $changed = FALSE;
    foreach ($xpath->query('//img[not(ancestor::figure)]|//drupal-media[not(ancestor::figure)]') ?: [] as $node) {
      if (!$node instanceof \DOMElement) {
        continue;
      }
      $is_managed = $node->tagName === 'drupal-media'
        || $node->hasAttribute('data-entity-uuid')
        || $node->getAttribute('data-entity-type') !== '';
      if (!$is_managed) {
        continue;
      }
      if ($node->getAttribute('data-align') === '') {
        $node->setAttribute('data-align', 'right');
        $changed = TRUE;
      }
      if (trim($node->getAttribute('data-caption')) === '') {
        $node->setAttribute('data-caption', ' ');
        $changed = TRUE;
      }
      $parent = $node->parentNode;
      if ($parent instanceof \DOMElement && strtolower($parent->tagName) === 'p' && self::onlyChild($parent, $node)) {
        $parent->parentNode?->replaceChild($node, $parent);
        $changed = TRUE;
      }
    }

    if (!$changed) {
      return $html;
    }
    $root = NULL;
    foreach ($dom->getElementsByTagName('div') as $div) {
      if ($div instanceof \DOMElement && $div->getAttribute('id') === 'df-editor-body') {
        $root = $div;
        break;
      }
    }
    if (!$root) {
      return $html;
    }
    $output = '';
    foreach ($root->childNodes as $child) {
      $output .= $dom->saveHTML($child);
    }
    return $output;
  }

  private static function onlyChild(\DOMElement $parent, \DOMNode $node): bool {
    foreach ($parent->childNodes as $child) {
      if ($child === $node) {
        continue;
      }
      if ($child instanceof \DOMText && trim($child->textContent) === '') {
        continue;
      }
      return FALSE;
    }
    return TRUE;
  }

}
