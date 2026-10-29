<?php

/**
 * @file
 * Backfills field_postx_sort_weight and field_homepage_sort_weight from WP HTML.
 *
 * Issue order: ultp-block-item post-id-* in reference-pack issue pages.
 * Homepage order: sections under Explore / Digital Exclusives / Recent Highlights.
 *
 * Usage: drush scr modules/custom/df_migrate/scripts/backfill_postx_sort_weights.php
 */

use Drupal\df_migrate\IssuePageWpFixture;
use Drupal\node\Entity\Node;

$pack_root = realpath('/var/www/parity/reference-pack')
  ?: realpath(DRUPAL_ROOT . '/../../parity/reference-pack')
  ?: realpath(dirname(DRUPAL_ROOT, 2) . '/parity/reference-pack');
$issues_json = __DIR__ . '/../data/issues.json';
$use_live_wp = !$pack_root || !is_dir($pack_root);

/**
 * @return string
 */
function df_postx_issue_page_html(string $slug, ?string $pack_root, bool $use_live_wp): string {
  $cache_roots = [
    dirname(DRUPAL_ROOT, 2) . '/parity/cache/wp-issue-pages',
    DRUPAL_ROOT . '/../../parity/cache/wp-issue-pages',
    '/workspace/parity/cache/wp-issue-pages',
  ];
  $file_slug = str_replace('/', '_', $slug);
  foreach ($cache_roots as $root) {
    $file = rtrim($root, '/') . '/' . $file_slug . '.html';
    if (is_readable($file)) {
      $body = file_get_contents($file);
      if (is_string($body) && strlen($body) > 5000 && !preg_match('/sgcaptcha/i', $body)) {
        return $body;
      }
    }
  }
  if (!$use_live_wp && $pack_root && is_dir($pack_root)) {
    $html_path = $pack_root . '/' . $file_slug . '/desktop/rendered.html';
    if (is_readable($html_path)) {
      return (string) file_get_contents($html_path);
    }
  }
  return df_postx_fetch_wp_html('/' . $slug);
}

function df_postx_fetch_wp_html(string $path): string {
  $url = 'https://danafarberimpact.org' . ($path === '/' ? '/' : rtrim($path, '/') . '/');
  $ctx = stream_context_create([
    'http' => [
      'timeout' => 45,
      'header' => "User-Agent: df-impact-postx-backfill\r\n",
    ],
  ]);
  $html = @file_get_contents($url, FALSE, $ctx);
  if (!is_string($html) || strlen($html) < 5000 || preg_match('/sgcaptcha/i', $html)) {
    return '';
  }
  return $html;
}

/**
 * @return int|null
 */
function df_postx_nid_for_wp_id(int $wp_id): ?int {
  static $map = NULL;
  if ($map === NULL) {
    $map = [];
    $rows = \Drupal::database()->select('migrate_map_df_articles', 'm')
      ->fields('m', ['sourceid1', 'destid1'])
      ->execute();
    foreach ($rows as $row) {
      $map[(int) $row->sourceid1] = (int) $row->destid1;
    }
  }
  return $map[$wp_id] ?? NULL;
}

/**
 * Main “In This Issue” PostX grid only (excludes promo grid below In Brief).
 */
function df_postx_issue_main_grid_html(string $html): string {
  $parts = preg_split('/<div class="ultp-post-grid-block/s', $html, 3);
  if (is_array($parts) && count($parts) >= 2) {
    return $parts[1];
  }
  return $html;
}

/**
 * @return list<int>
 */
function df_postx_parse_grid_post_ids(string $html): array {
  if (!preg_match_all('/ultp-block-item post-id-(\d+)/', $html, $m)) {
    return [];
  }
  return array_values(array_unique(array_map('intval', $m[1])));
}

/**
 * @return list<int>
 */
function df_postx_parse_issue_main_grid_post_ids(string $html): array {
  return df_postx_parse_grid_post_ids(df_postx_issue_main_grid_html($html));
}

/**
 * @return array<string, list<int>>
 */
function df_postx_parse_homepage_sections(string $html): array {
  $sections = [
    'explore' => [],
    'digital' => [],
    'highlights' => [],
  ];
  if (!preg_match('/Explore the Latest Issue.*?<\/h2>(.*?)Digital Exclusives/s', $html, $m)) {
    return $sections;
  }
  $sections['explore'] = df_postx_parse_grid_post_ids($m[1]);
  if (preg_match('/Digital Exclusives.*?<\/h2>(.*?)Recent Highlights/s', $html, $m2)) {
    $sections['digital'] = df_postx_parse_grid_post_ids($m2[1]);
  }
  if (preg_match('/Recent Highlights.*?<\/h2>(.*?)(?:<footer|<\/body)/s', $html, $m3)) {
    $sections['highlights'] = df_postx_parse_grid_post_ids($m3[1]);
  }
  return $sections;
}

$issue_updated = 0;
$home_updated = 0;

$fixture_index = IssuePageWpFixture::indexByAlias();

if (is_readable($issues_json)) {
  $issues = json_decode(file_get_contents($issues_json), TRUE);
  if (is_array($issues)) {
    foreach ($issues as $issue_row) {
      $alias = $issue_row['path_alias'] ?? '';
      if (!is_string($alias) || $alias === '') {
        continue;
      }
      $slug = trim($alias, '/');
      $norm = '/' . $slug . '/';
      $wp_ids = [];
      if (isset($fixture_index[$norm]['main_grid_wp_post_ids']) && is_array($fixture_index[$norm]['main_grid_wp_post_ids'])) {
        $wp_ids = array_map('intval', $fixture_index[$norm]['main_grid_wp_post_ids']);
      }
      if ($wp_ids === []) {
        $html = df_postx_issue_page_html($slug, $pack_root, $use_live_wp);
        if ($html === '') {
          continue;
        }
        $wp_ids = df_postx_parse_issue_main_grid_post_ids($html);
      }
      if ($wp_ids === []) {
        continue;
      }
      foreach ($wp_ids as $index => $wp_id) {
        $nid = df_postx_nid_for_wp_id($wp_id);
        if ($nid === NULL) {
          continue;
        }
        $node = Node::load($nid);
        if (!$node || !$node->hasField('field_postx_sort_weight')) {
          continue;
        }
        $weight = ($index + 1) * 10;
        if ((int) $node->get('field_postx_sort_weight')->value !== $weight) {
          $node->set('field_postx_sort_weight', $weight);
          $node->save();
          $issue_updated++;
        }
      }
    }
  }
}

$homepage_html = $use_live_wp ? '' : ($pack_root . '/homepage/desktop/rendered.html');
$homepage_body = '';
if ($homepage_html !== '' && is_readable($homepage_html)) {
  $homepage_body = file_get_contents($homepage_html);
}
else {
  $homepage_body = df_postx_fetch_wp_html('/homepage');
}
if ($homepage_body !== '') {
  $sections = df_postx_parse_homepage_sections($homepage_body);
  $bases = [
    'explore' => 10,
    'digital' => 110,
    'highlights' => 210,
  ];
  foreach ($sections as $key => $wp_ids) {
    $base = $bases[$key] ?? 500;
    foreach ($wp_ids as $index => $wp_id) {
      $nid = df_postx_nid_for_wp_id($wp_id);
      if ($nid === NULL) {
        continue;
      }
      $node = Node::load($nid);
      if (!$node || !$node->hasField('field_homepage_sort_weight')) {
        continue;
      }
      $weight = $base + ($index * 10);
      $changed = (int) $node->get('field_homepage_sort_weight')->value !== $weight;
      if ($changed) {
        $node->set('field_homepage_sort_weight', $weight);
      }
      if ($key === 'highlights' && $node->hasField('field_homepage_placement')) {
        if ($node->get('field_homepage_placement')->value !== 'recent_highlights') {
          $node->set('field_homepage_placement', 'recent_highlights');
          $changed = TRUE;
        }
      }
      if ($changed) {
        $node->save();
        $home_updated++;
      }
    }
  }
}

\Drupal::service('cache_tags.invalidator')->invalidateTags(['node_list:article']);
echo "PostX sort backfill: issue weights on {$issue_updated} saves; homepage on {$home_updated} saves.\n";
