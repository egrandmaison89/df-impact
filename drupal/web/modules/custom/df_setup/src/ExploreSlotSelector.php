<?php

declare(strict_types=1);

namespace Drupal\df_setup;

/**
 * Picks the three Explore the Latest Issue slots for one issue.
 *
 * Featured articles occupy slots 1–3. An empty slot (unpublished, missing, or
 * a Digital Exclusive) is filled from the same issue by publication date.
 */
final class ExploreSlotSelector {

  /**
   * @param list<array{nid:int,placement:string,order:int,published:bool,digital_exclusive:bool,sort:int,changed:int}> $rows
   *
   * @return list<int>
   *   Up to three node IDs in slot order (large, then the two stacked cards).
   */
  public static function select(array $rows): array {
    $eligible = array_values(array_filter(
      $rows,
      static fn (array $row): bool => !empty($row['published']) && empty($row['digital_exclusive']),
    ));
    $slots = [1 => NULL, 2 => NULL, 3 => NULL];
    foreach ([1, 2, 3] as $slot) {
      $claimants = array_values(array_filter(
        $eligible,
        static fn (array $row): bool => ($row['placement'] ?? '') === 'featured' && (int) ($row['order'] ?? 0) === $slot,
      ));
      if ($claimants === []) {
        continue;
      }
      usort($claimants, static fn (array $a, array $b): int => ((int) $b['changed']) <=> ((int) $a['changed']));
      $slots[$slot] = (int) $claimants[0]['nid'];
    }
    $used = array_filter($slots, static fn ($nid): bool => $nid !== NULL);
    $backfill = array_values(array_filter(
      $eligible,
      static fn (array $row): bool => !in_array((int) $row['nid'], $used, TRUE),
    ));
    usort($backfill, static fn (array $a, array $b): int => ((int) $b['sort']) <=> ((int) $a['sort']));
    $index = 0;
    foreach ([1, 2, 3] as $slot) {
      if ($slots[$slot] === NULL && isset($backfill[$index])) {
        $slots[$slot] = (int) $backfill[$index]['nid'];
        $index++;
      }
    }
    return array_values(array_filter($slots, static fn ($nid): bool => $nid !== NULL));
  }

}
