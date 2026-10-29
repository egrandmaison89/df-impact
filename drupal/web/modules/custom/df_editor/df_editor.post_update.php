<?php

/**
 * @file
 * Post updates for df_editor (site chrome settings).
 */

declare(strict_types=1);

/**
 * Seeds DEI, legal bar, GTranslate disclaimer, and Issues menu label defaults.
 */
function df_editor_post_update_seed_chrome_settings_defaults(): string {
  require_once __DIR__ . '/df_editor.install';
  return _df_editor_ensure_chrome_settings_defaults(FALSE);
}
