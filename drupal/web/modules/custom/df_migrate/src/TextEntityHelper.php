<?php

declare(strict_types=1);

namespace Drupal\df_migrate;

/**
 * Normalizes plain text that may contain HTML character references.
 */
final class TextEntityHelper {

  /**
   * Decodes HTML entities for storage/display as plain UTF-8 text.
   */
  public static function decode(?string $text): string {
    if ($text === NULL || $text === '') {
      return '';
    }
    $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // Repeat once when exports double-encoded (e.g. &amp;#039;).
    if (str_contains($decoded, '&') && preg_match('/&#?\w+;/', $decoded)) {
      $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return $decoded;
  }

  /**
   * Counts HTML entity references remaining in a string.
   */
  public static function countEntityReferences(string $text): int {
    if ($text === '') {
      return 0;
    }
    preg_match_all('/&(?:#x?[0-9a-f]+|[a-z]+);/i', $text, $matches);
    return count($matches[0] ?? []);
  }

}
