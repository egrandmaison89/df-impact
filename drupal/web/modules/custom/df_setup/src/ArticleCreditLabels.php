<?php

declare(strict_types=1);

namespace Drupal\df_setup;

/**
 * Byline and photo-credit prefixes (display only; fields store names).
 */
final class ArticleCreditLabels {

  public const BYLINE_PREFIX = 'By ';

  public const PHOTO_CREDIT_PREFIX = 'Photography by ';

  /**
   * Strips a stored or typed byline down to the author name.
   */
  public static function stripByline(string $value): string {
    $value = trim($value);
    if ($value === '') {
      return '';
    }
    return trim((string) preg_replace('/^By\s+/i', '', $value));
  }

  /**
   * Strips a stored or typed photo credit down to the photographer name.
   */
  public static function stripPhotoCredit(string $value): string {
    $value = trim($value);
    if ($value === '') {
      return '';
    }
    return trim((string) preg_replace('/^Photograph(?:y)?\s+by\s+/i', '', $value));
  }

  /**
   * Formats an author name for front-end display.
   */
  public static function formatByline(string $name): string {
    $name = self::stripByline($name);
    return $name === '' ? '' : self::BYLINE_PREFIX . $name;
  }

  /**
   * Formats a photographer name for front-end display.
   */
  public static function formatPhotoCredit(string $name): string {
    $name = self::stripPhotoCredit($name);
    return $name === '' ? '' : self::PHOTO_CREDIT_PREFIX . $name;
  }

}
