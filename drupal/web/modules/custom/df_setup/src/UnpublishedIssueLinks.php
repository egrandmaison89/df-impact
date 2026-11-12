<?php

declare(strict_types=1);

namespace Drupal\df_setup;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Public links to an unpublished issue go to Browse issues.
 *
 * Stories stay published. Anonymous requests for the issue itself redirect.
 * Editors who can view unpublished content still open the issue, and get a
 * warning when they unpublish it. Late Fall 2022 is left untouched.
 */
final class UnpublishedIssueLinks {

  /**
   * TRUE when this account may open an unpublished issue.
   */
  public static function accountCanView(AccountInterface $account): bool {
    return $account->hasPermission('bypass node access')
      || $account->hasPermission('view any unpublished content');
  }

  /**
   * Browse issues URL used in place of an unpublished issue.
   */
  public static function browseUrl(): string {
    return Url::fromRoute('view.browse_issues.page_1')->toString();
  }

  /**
   * URL to render for an issue link.
   *
   * Editors who can view the unpublished issue keep its URL. Everyone else,
   * and every public render of a story, uses Browse issues.
   */
  public static function urlForIssue(NodeInterface $issue, bool $public_render = FALSE): string {
    if ($issue->bundle() !== 'issue' || self::isLegacyFixture($issue)) {
      return $issue->toUrl()->toString();
    }
    if ($issue->isPublished()) {
      return $issue->toUrl()->toString();
    }
    if (!$public_render && self::accountCanView(\Drupal::currentUser())) {
      return $issue->toUrl()->toString();
    }
    return self::browseUrl();
  }

  /**
   * TRUE when the path is the canonical path or alias of an unpublished issue.
   */
  public static function isUnpublishedIssuePath(string $path): bool {
    $path = self::normalizePath($path);
    if ($path === NULL) {
      return FALSE;
    }
    $paths = self::unpublishedPaths();
    if (isset($paths[$path])) {
      return TRUE;
    }
    $internal = \Drupal::service('path_alias.manager')->getPathByAlias($path);
    return is_string($internal) && isset($paths[self::normalizePath($internal) ?? '']);
  }

  /**
   * Replaces hrefs that point at an unpublished issue.
   */
  public static function rewriteHtml(string $html): string {
    if ($html === '' || !str_contains($html, 'href=')) {
      return $html;
    }
    return (string) preg_replace_callback(
      '#href=(["\'])([^"\']+)\1#i',
      static function (array $matches): string {
        $url = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!self::isUnpublishedIssuePath($url)) {
          return $matches[0];
        }
        return 'href=' . $matches[1] . self::browseUrl() . $matches[1];
      },
      $html,
    );
  }

  /**
   * Late Fall 2022 stays on its existing route behavior.
   */
  public static function isLegacyFixture(NodeInterface $issue): bool {
    if (!$issue->id() || !function_exists('df_setup_legacy_issue_tag_slug_for_request_path')) {
      return FALSE;
    }
    $alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $issue->id());
    return df_setup_legacy_issue_tag_slug_for_request_path((string) $alias) !== '';
  }

  /**
   * Drops the path cache after an issue is published or unpublished.
   */
  public static function reset(): void {
    drupal_static_reset('df_setup_unpublished_issue_paths');
  }

  /**
   * @return array<string, true>
   *   System paths and aliases of unpublished issues, excluding Late Fall.
   */
  private static function unpublishedPaths(): array {
    $paths = &drupal_static('df_setup_unpublished_issue_paths');
    if (is_array($paths)) {
      return $paths;
    }
    $paths = [];
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $nids = $storage->getQuery()
      ->condition('type', 'issue')
      ->condition('status', 0)
      ->accessCheck(FALSE)
      ->execute();
    if ($nids === []) {
      return $paths;
    }
    $alias_manager = \Drupal::service('path_alias.manager');
    foreach ($storage->loadMultiple($nids) as $node) {
      if (!$node instanceof NodeInterface || self::isLegacyFixture($node)) {
        continue;
      }
      $system = '/node/' . $node->id();
      $paths[$system] = TRUE;
      $alias = $alias_manager->getAliasByPath($system);
      $normalized = self::normalizePath((string) $alias);
      if ($normalized !== NULL) {
        $paths[$normalized] = TRUE;
      }
    }
    return $paths;
  }

  private static function normalizePath(string $path): ?string {
    $parsed = parse_url($path, PHP_URL_PATH);
    if (!is_string($parsed) || $parsed === '') {
      if (str_starts_with($path, '/')) {
        $parsed = $path;
      }
      else {
        return NULL;
      }
    }
    $parsed = '/' . ltrim($parsed, '/');
    if ($parsed !== '/') {
      $parsed = rtrim($parsed, '/');
    }
    return $parsed;
  }

}
