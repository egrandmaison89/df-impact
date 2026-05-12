<?php

declare(strict_types=1);

namespace Drupal\df_migrate\Drush\Commands;

use Drupal\node\NodeInterface;
use Drush\Attributes\Bootstrap;
use Drush\Attributes\Command;
use Drush\Attributes\Option;
use Drush\Attributes\Usage;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for df_migrate.
 */
final class DfMigrateCommands extends DrushCommands {

  /**
   * Backfill article featured images from WordPress export (articles.json).
   */
  #[Command(name: 'df-migrate:backfill-article-featured')]
  #[Bootstrap(level: DrupalBootLevels::FULL)]
  #[Usage(name: 'drush df-migrate:backfill-article-featured --dry-run', description: 'Preview changes')]
  #[Usage(name: 'drush df-migrate:backfill-article-featured --live-fallback', description: 'Use live og:image when URLs fail')]
  #[Option(name: 'dry-run', description: 'Show what would change without saving.')]
  #[Option(name: 'live-fallback', description: 'If export URLs fail, fetch og:image from the live WordPress article.')]
  #[Option(name: 'only-wp-id', description: 'Restrict to one WordPress source id (wp_id from articles.json).')]
  public function backfillArticleFeatured(array $options = [
    'dry-run' => FALSE,
    'live-fallback' => FALSE,
    'only-wp-id' => NULL,
  ]): void {
    $inc = dirname(__DIR__, 3) . '/scripts/backfill_article_featured_images_from_wordpress.inc';
    if (!is_readable($inc)) {
      $this->logger()->error(dt('Missing include file @f', ['@f' => $inc]));
      return;
    }
    require_once $inc;

    $only = $options['only-wp-id'];
    $only_wp_id = ($only !== NULL && $only !== '' && (string) $only !== '0')
      ? (int) $only
      : NULL;

    df_migrate_backfill_article_featured_images_run(
      (bool) $options['dry-run'],
      (bool) $options['live-fallback'],
      $only_wp_id,
    );
  }

  /**
   * Remove migrated content whose slug no longer appears on the WordPress site.
   *
   * Requires modules/custom/df_migrate/data/wp_sitemap_state.json from
   * migration-data/fetch_wordpress_model.py. Only nodes listed in migrate
   * map tables (df_articles / df_in_brief) are considered; native Drupal
   * content is untouched.
   */
  #[Command(name: 'df-migrate:prune-wordpress-orphans')]
  #[Bootstrap(level: DrupalBootLevels::FULL)]
  #[Usage(name: 'drush df-migrate:prune-wordpress-orphans', description: 'List nodes that would be removed (dry run).')]
  #[Usage(name: 'drush df-migrate:prune-wordpress-orphans --execute', description: 'Delete orphaned nodes and map rows.')]
  #[Option(name: 'execute', description: 'Perform deletions. Without this flag, only prints candidates.')]
  public function pruneWordpressOrphans(array $options = ['execute' => FALSE]): void {
    $data_file = \Drupal::root() . '/modules/custom/df_migrate/data/wp_sitemap_state.json';
    if (!is_readable($data_file)) {
      $this->logger()->error(dt('Missing @f — run: python3 drupal/migration-data/fetch_wordpress_model.py [--sitemap-only]', ['@f' => $data_file]));
      return;
    }
    $raw = json_decode((string) file_get_contents($data_file), TRUE);
    $slugs = $raw['post_slugs'] ?? [];
    if (!is_array($slugs) || $slugs === []) {
      $this->logger()->error(dt('Invalid or empty post_slugs in wp_sitemap_state.json'));
      return;
    }
    $allowed = [];
    foreach ($slugs as $s) {
      if (is_string($s) && $s !== '') {
        $allowed[strtolower($s)] = TRUE;
      }
    }

    $alias_manager = \Drupal::service('path_alias.manager');
    $database = \Drupal::database();
    $storage = \Drupal::entityTypeManager()->getStorage('node');

    $specs = [
      [
        'bundle' => 'article',
        'table' => 'migrate_map_df_articles',
        'prefix' => '/stories/',
      ],
      [
        'bundle' => 'in_brief',
        'table' => 'migrate_map_df_in_brief',
        'prefix' => '/in-brief/',
      ],
    ];

    $to_remove = [];

    foreach ($specs as $spec) {
      if (!$database->schema()->tableExists($spec['table'])) {
        $this->logger()->warning(dt('Skip @t (table missing)', ['@t' => $spec['table']]));
        continue;
      }
      $map_rows = $database->select($spec['table'], 'm')
        ->fields('m', ['sourceid1', 'destid1'])
        ->execute()
        ->fetchAllAssoc('destid1', \PDO::FETCH_ASSOC);

      foreach ($map_rows as $nid => $row) {
        $nid = (int) $nid;
        $node = $storage->load($nid);
        if (!$node instanceof NodeInterface || $node->bundle() !== $spec['bundle']) {
          continue;
        }
        $internal = '/node/' . $nid;
        $langcode = $node->language()->getId();
        $alias = $alias_manager->getAliasByPath($internal, $langcode);
        if (!is_string($alias) || $alias === '' || $alias === $internal) {
          continue;
        }
        $alias = '/' . trim($alias, '/');
        $np = rtrim($spec['prefix'], '/');
        if (!str_starts_with($alias, $np . '/') && $alias !== $np) {
          continue;
        }
        $slug_part = $alias === $np ? '' : substr($alias, strlen($np) + 1);
        $slug = strtolower(explode('/', trim($slug_part, '/'))[0] ?? '');
        if ($slug === '') {
          continue;
        }
        if (isset($allowed[$slug])) {
          continue;
        }
        $to_remove[] = [
          'bundle' => $spec['bundle'],
          'table' => $spec['table'],
          'nid' => $nid,
          'wp_id' => (int) ($row['sourceid1'] ?? 0),
          'slug' => $slug,
        ];
      }
    }

    if ($to_remove === []) {
      $this->logger()->success(dt('No migrated article/in-brief nodes look orphaned vs wp_sitemap_state.json.'));
      return;
    }

    foreach ($to_remove as $item) {
      $this->output()->writeln(dt('Orphan: nid @nid (@bundle) wp_id @wp slug @s', [
        '@nid' => $item['nid'],
        '@bundle' => $item['bundle'],
        '@wp' => $item['wp_id'],
        '@s' => $item['slug'],
      ]));
    }

    $execute = !empty($options['execute']);
    if (!$execute) {
      $this->logger()->notice(dt('Dry run only. Re-run with --execute to delete @n nodes and map rows.', ['@n' => count($to_remove)]));
      return;
    }

    foreach ($to_remove as $item) {
      $database->delete($item['table'])
        ->condition('destid1', $item['nid'])
        ->execute();
      $node = $storage->load($item['nid']);
      if ($node instanceof NodeInterface) {
        $node->delete();
      }
    }

    drupal_flush_all_caches();
    $this->logger()->success(dt('Removed @n orphaned nodes; caches cleared.', ['@n' => count($to_remove)]));
  }

  /**
   * Sync article/in-brief paths, placement, and redirects from df_migrate JSON.
   */
  #[Command(name: 'df-migrate:sync-wordpress-paths')]
  #[Bootstrap(level: DrupalBootLevels::FULL)]
  #[Usage(name: 'drush df-migrate:sync-wordpress-paths', description: 'Run after updating articles.json / in_brief.json / redirects.json')]
  public function syncWordpressPaths(): void {
    $inc = dirname(__DIR__, 3) . '/scripts/df_sync_wordpress_paths.inc';
    if (!is_readable($inc)) {
      $this->logger()->error(dt('Missing @f', ['@f' => $inc]));
      return;
    }
    require_once $inc;
    df_migrate_run_sync_wordpress_paths();
  }

}
