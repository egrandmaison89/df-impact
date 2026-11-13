<?php

declare(strict_types=1);

namespace Drupal\df_setup;

/**
 * Issue-page story order: pin, then publication date, with an optional demote.
 */
final class IssueStoryOrder {

  public static function rankValue(string $rank): int {
    return match ($rank) {
      'pinned' => 0,
      'demoted' => 2,
      default => 1,
    };
  }

  /**
   * Lower rank first. Within a rank, newer publication timestamps first.
   */
  public static function compare(int $rank_a, int $sort_a, int $rank_b, int $sort_b): int {
    if ($rank_a !== $rank_b) {
      return $rank_a <=> $rank_b;
    }
    return $sort_b <=> $sort_a;
  }

}
