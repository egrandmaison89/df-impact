<?php

declare(strict_types=1);

namespace Drupal\df_setup\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Serves legacy issue URLs that are not yet path aliases.
 */
final class LegacyIssueController extends ControllerBase {

  /**
   * Late Fall 2022 issue, or its tag archive when the issue node is absent.
   */
  public function lateFall2022(Request $request): array {
    $slug = 'late-fall-2022';
    $request->attributes->set('df_setup_wp_archive_path', '/tag/' . $slug);
    $argument = function_exists('df_setup_topic_term_tids_for_category_slug') && df_setup_topic_term_tids_for_category_slug($slug) !== []
      ? $slug
      : 'all';
    $build = views_embed_view('topic_archive', 'page_1', $argument);
    if (!is_array($build)) {
      return [
        '#markup' => '',
        '#cache' => ['contexts' => ['url.path']],
      ];
    }
    return $build;
  }

}
