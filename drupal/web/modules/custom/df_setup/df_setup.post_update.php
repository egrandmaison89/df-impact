<?php

/**
 * @file
 * Post update functions for DF Setup (parity backfills without schema numbers).
 */

declare(strict_types=1);

/**
 * Seed issue field_cover_image from committed wp-images / WP uploads (browse-issues).
 */
function df_setup_post_update_seed_issue_covers(&$sandbox): string {
  return _df_setup_backfill_issue_cover_images();
}

/**
 * Seed issue field_banner_image when empty (issue landing hero parity).
 */
function df_setup_post_update_seed_issue_banners(&$sandbox): string {
  return _df_setup_backfill_issue_banner_images();
}
