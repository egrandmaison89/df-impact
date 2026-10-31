<?php

/**
 * @file
 * Clears issue banner/cover/promo fields and replays fixture backfills (no live WP).
 *
 * Usage: drush scr modules/custom/df_migrate/scripts/test_issue_fixture_replay.php
 */

use Drupal\node\Entity\Node;

$nids = \Drupal::entityQuery('node')
  ->condition('type', 'issue')
  ->accessCheck(FALSE)
  ->execute();

$cleared = 0;
foreach ($nids as $nid) {
  $issue = Node::load($nid);
  if (!$issue) {
    continue;
  }
  $issue->set('field_banner_image', []);
  $issue->set('field_cover_image', []);
  $issue->set('field_leadership_message', []);
  $issue->set('field_issue_promo_2', []);
  $issue->save();
  $cleared++;
}

echo "Cleared banner/cover/promo on $cleared issue nodes.\n";

require __DIR__ . '/backfill_postx_sort_weights.php';
require __DIR__ . '/backfill_issue_page_from_wordpress.php';

echo "Replay complete.\n";
