<?php

/**
 * @file
 * Failure checks for the In Brief archive (grouped by issue on /in-brief).
 *
 * Run from the Composer/drupal directory after cache rebuild:
 *   ./vendor/bin/drush --uri=http://df-impact.ddev.site php:script scripts/verify-in-brief-listing.php
 *
 * Exit code 1 if any check fails.
 */

declare(strict_types=1);

use Drupal\views\Views;

/**
 * Runs In Brief listing checks; returns non-empty strings describing failures.
 *
 * @return list<string>
 */
function df_impact_verify_in_brief_listing_failures(): array {
  $failures = [];

  if (!function_exists('df_impact_group_in_brief_nodes_by_issue')) {
    $failures[] = 'df_impact theme is not loaded (helpers missing). Ensure default theme is df_impact.';
    return $failures;
  }

  $grouped = df_impact_group_in_brief_nodes_by_issue();
  $total_briefs = 0;
  foreach ($grouped as $row) {
    $total_briefs += count($row['briefs']);
  }

  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $published_count = (int) $storage->getQuery()
    ->condition('type', 'in_brief')
    ->condition('status', 1)
    ->accessCheck()
    ->count()
    ->execute();

  if ($published_count > 0 && $total_briefs === 0) {
    $failures[] = 'Published In Brief nodes exist but grouping returned none (likely missing or invalid field_issue).';
  }

  $sections = df_impact_build_in_brief_listing_sections($grouped);
  $theme_build = [
    '#theme' => 'in_brief_listing_by_issue',
    '#sections' => $sections,
  ];
  $themed = (string) \Drupal::service('renderer')->renderRoot($theme_build);
  if ($grouped !== [] && $themed === '') {
    $failures[] = 'Theme in_brief_listing_by_issue rendered empty while grouped data exists (check #sections variable keys).';
  }
  if ($grouped !== [] && !str_contains($themed, 'in-brief-page__issue-heading')) {
    $failures[] = 'Theme output missing .in-brief-page__issue-heading (template or variables broken).';
  }

  $view = Views::getView('in_brief_listing');
  if (!$view) {
    $failures[] = 'View in_brief_listing not found.';
    return $failures;
  }

  $build = views_embed_view('in_brief_listing', 'page_1');
  if (!is_array($build)) {
    $failures[] = 'views_embed_view(in_brief_listing, page_1) did not return a render array (access denied or missing display).';
    return $failures;
  }

  $page_html = (string) \Drupal::service('renderer')->renderRoot($build);
  if ($total_briefs > 0 && !str_contains($page_html, 'in-brief-page__issue-heading')) {
    $failures[] = 'Full page view render missing issue headings; views_post_render or #rows structure may be wrong.';
  }

  return $failures;
}

$failures = df_impact_verify_in_brief_listing_failures();
if ($failures !== []) {
  fwrite(STDERR, "verify-in-brief-listing FAILED:\n- " . implode("\n- ", $failures) . "\n");
  exit(1);
}

fwrite(STDOUT, "verify-in-brief-listing: OK\n");
exit(0);
