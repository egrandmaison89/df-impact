<?php

/**
 * @file
 * Name-based post updates for df_setup after schema **10035** (frozen).
 *
 * Do not add df_setup_update_10036+ in df_setup.install. Drupal tracks
 * post_update hooks by function name, so ordering merges no longer skips work.
 *
 * Expected from upcoming whole merges (implement on those branches here):
 * - **#23** content-sweep: backfills that were numbered 10031–10033.
 * - **#25** cover / media seed for issue parity.
 * - **#10** `is_light` field: config import + backfill post-update (stacked on #25).
 */
