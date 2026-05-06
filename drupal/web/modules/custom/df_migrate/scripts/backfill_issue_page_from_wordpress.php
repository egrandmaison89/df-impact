<?php

/**
 * @file
 * Backfills Issue fields from WordPress issue pages: banner image, promos.
 *
 * Parses https://danafarberimpact.org/{slug}-issue/ for:
 * - og:image / cover image → field_banner_image (media)
 * WordPress promo URLs use /YYYY/MM/slug; Drupal aliases are /stories/slug (see
 * articles.json). Resolution uses articles.json + redirects.json, then path_alias.
 *
 * Usage (Drupal docroot, e.g. ddev drush scr …):
 *   drush scr modules/custom/df_migrate/scripts/backfill_issue_page_from_wordpress.php
 *   drush scr modules/custom/df_migrate/scripts/backfill_issue_page_from_wordpress.php -- --dry-run
 *   drush scr modules/custom/df_migrate/scripts/backfill_issue_page_from_wordpress.php -- --skip-existing
 *   drush scr modules/custom/df_migrate/scripts/backfill_issue_page_from_wordpress.php -- --only-nid=123
 */

use Drupal\df_migrate\Plugin\migrate\process\DownloadImage;
use Drupal\file\FileInterface;
use Drupal\media\Entity\Media;
use Drupal\node\NodeInterface;

$argv = $_SERVER['argv'] ?? [];
$dry_run = in_array('--dry-run', $argv, TRUE);
$skip_existing = in_array('--skip-existing', $argv, TRUE);
$only_nid = NULL;
foreach ($argv as $arg) {
  if (str_starts_with($arg, '--only-nid=')) {
    $only_nid = (int) substr($arg, 11);
  }
}

const WP_HOST = 'https://danafarberimpact.org';

$node_storage = \Drupal::entityTypeManager()->getStorage('node');
$query = $node_storage->getQuery()
  ->condition('type', 'issue')
  ->condition('status', 1)
  ->accessCheck(FALSE)
  ->sort('field_year', 'DESC')
  ->sort('field_season', 'DESC');
if ($only_nid) {
  $query->condition('nid', $only_nid);
}
$nids = $query->execute();

$updated = 0;
$errors = 0;

foreach ($nids as $nid) {
  $nid = (int) $nid;
  /** @var \Drupal\node\NodeInterface|null $issue */
  $issue = $node_storage->load($nid);
  if (!$issue instanceof NodeInterface) {
    continue;
  }

  $wp_url = df_issue_page_wordpress_url($issue);
  if ($wp_url === '') {
    fwrite(STDERR, "Skip nid $nid: could not build WordPress URL.\n");
    continue;
  }

  if ($skip_existing
    && !$issue->get('field_banner_image')->isEmpty()
    && !$issue->get('field_leadership_message')->isEmpty()
    && !$issue->get('field_issue_promo_2')->isEmpty()) {
    echo "Skip (complete): {$issue->label()} (nid $nid)\n";
    continue;
  }

  $need_banner = !$skip_existing || $issue->get('field_banner_image')->isEmpty();
  $need_promos = !$skip_existing
    || $issue->get('field_leadership_message')->isEmpty()
    || $issue->get('field_issue_promo_2')->isEmpty();

  if (!$need_banner && !$need_promos) {
    continue;
  }

  echo "Fetch: $wp_url\n";
  $html = df_issue_page_http_get($wp_url);
  if ($html === '') {
    fwrite(STDERR, "  Failed to fetch.\n");
    $errors++;
    continue;
  }

  $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  $dom = new DOMDocument();
  $internal = libxml_use_internal_errors(TRUE);
  $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
  libxml_use_internal_errors($internal);
  if (!$loaded) {
    fwrite(STDERR, "  Parse error.\n");
    $errors++;
    continue;
  }

  $xpath = new DOMXPath($dom);
  $dirty = FALSE;

  if ($need_banner) {
    $banner_url = df_issue_page_parse_banner_url($xpath);
    if ($banner_url === '') {
      fwrite(STDERR, "  No banner URL found.\n");
    }
    else {
      $alt = $issue->label() . ' issue banner';
      echo ($dry_run ? '[dry-run] ' : '') . "  Banner <= $banner_url\n";
      if (!$dry_run) {
        $fid = DownloadImage::getOrCreateFileFromUrl($banner_url);
        if ($fid === NULL) {
          fwrite(STDERR, "  Banner download failed.\n");
          $errors++;
        }
        else {
          $mid = df_issue_page_get_or_create_media($fid, $alt);
          if ($mid === NULL) {
            fwrite(STDERR, "  Banner media failed.\n");
            $errors++;
          }
          else {
            $issue->set('field_banner_image', ['target_id' => $mid]);
            $dirty = TRUE;
          }
        }
      }
    }
  }

  if ($need_promos) {
    $hrefs = df_issue_page_parse_promo_article_urls($xpath);
    $promo_nids = [];
    foreach ($hrefs as $href) {
      $mapped = df_issue_page_wp_url_to_node_id($href);
      if ($mapped === NULL) {
        fwrite(STDERR, "  No Drupal node for promo URL: $href\n");
        continue;
      }
      /** @var \Drupal\node\NodeInterface|null $promo_node */
      $promo_node = $node_storage->load($mapped);
      if (!$promo_node instanceof NodeInterface) {
        continue;
      }
      if (!in_array($promo_node->bundle(), ['article', 'page'], TRUE)) {
        fwrite(STDERR, "  Promo target not article/page: $href → {$promo_node->bundle()}\n");
        continue;
      }
      $promo_nids[] = $mapped;
      if (count($promo_nids) >= 2) {
        break;
      }
    }

    if ($promo_nids !== []) {
      echo ($dry_run ? '[dry-run] ' : '') . '  Promos => ' . implode(', ', $promo_nids) . "\n";
      if (!$dry_run) {
        if (!empty($promo_nids[0])) {
          $issue->set('field_leadership_message', ['target_id' => $promo_nids[0]]);
        }
        if (!empty($promo_nids[1])) {
          $issue->set('field_issue_promo_2', ['target_id' => $promo_nids[1]]);
        }
        $dirty = TRUE;
      }
    }
    else {
      fwrite(STDERR, "  No promo articles resolved.\n");
    }
  }

  if (!$dry_run && $dirty) {
    try {
      $issue->save();
      $updated++;
    }
    catch (\Throwable $e) {
      fwrite(STDERR, "  Save failed: " . $e->getMessage() . "\n");
      $errors++;
    }
  }

  sleep(1);
}

echo "\nDone. Issues updated: $updated; errors: $errors\n";

if (!$dry_run && $updated > 0) {
  drupal_flush_all_caches();
  echo "Caches rebuilt.\n";
}

/**
 * Builds https://danafarberimpact.org/{slug}-issue/ from path alias or fields.
 */
function df_issue_page_wordpress_url(NodeInterface $issue): string {
  $alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $issue->id());
  $slug = '';
  if ($alias && $alias !== '/node/' . $issue->id()) {
    $path = (string) (parse_url($alias, PHP_URL_PATH) ?? $alias);
    if (preg_match('#/issues/([^/]+)$#', $path, $m)) {
      $slug = strtolower(str_replace(' ', '-', rawurldecode($m[1])));
    }
  }
  if ($slug === '') {
    $season = (string) $issue->get('field_season')->value;
    $year = (string) $issue->get('field_year')->value;
    if ($season === '' || $year === '') {
      return '';
    }
    $slug = str_replace(' ', '-', $season) . '-' . $year;
  }
  return WP_HOST . '/' . $slug . '-issue/';
}

function df_issue_page_http_get(string $url): string {
  $ctx = stream_context_create([
    'http' => [
      'timeout' => 60,
      'user_agent' => 'Drupal/11 DFMigrate/issue-page-backfill',
    ],
    'ssl' => [
      'verify_peer' => TRUE,
      'verify_peer_name' => TRUE,
    ],
  ]);
  $data = @file_get_contents($url, FALSE, $ctx);
  return is_string($data) ? $data : '';
}

function df_issue_page_parse_banner_url(DOMXPath $xpath): string {
  $meta = $xpath->query("//meta[@property='og:image']/@content");
  if ($meta->length > 0) {
    $u = trim((string) $meta->item(0)->nodeValue);
    if ($u !== '' && str_contains(strtolower($u), 'upload')) {
      return df_issue_page_canonical_url($u);
    }
  }
  /** @var DOMNodeList $imgs */
  $imgs = $xpath->query("//img[contains(@class,'wp-block-cover__image-background')]");
  for ($i = 0; $i < $imgs->length; $i++) {
    /** @var DOMElement $el */
    $el = $imgs->item($i);
    foreach ([$el->getAttribute('data-src'), $el->getAttribute('src')] as $raw) {
      $raw = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
      if ($raw === '' || str_starts_with($raw, 'data:')) {
        continue;
      }
      return df_issue_page_canonical_url($raw);
    }
  }
  return '';
}

/**
 * Featured promos: second post grid on the page (Stackables sit between grids).
 */
function df_issue_page_parse_promo_article_urls(DOMXPath $xpath): array {
  $blocks = $xpath->query("//div[contains(@class,'ultp-post-grid-block')]");
  $target = NULL;
  for ($i = 0; $i < $blocks->length; $i++) {
    /** @var DOMElement $block */
    $block = $blocks->item($i);
    $class = $block->getAttribute('class');
    if (str_contains($class, 'ultp-block-42b646')) {
      $target = $block;
      break;
    }
  }
  if (!$target instanceof DOMElement && $blocks->length >= 2) {
    /** @var DOMElement $last */
    $last = $blocks->item($blocks->length - 1);
    $target = $last instanceof DOMElement ? $last : NULL;
  }
  if (!$target instanceof DOMElement) {
    return [];
  }
  $hrefs = [];
  $seen = [];
  $anchors = $xpath->query(".//div[contains(@class,'ultp-block-item')]//h2//a/@href", $target);
  for ($j = 0; $j < $anchors->length; $j++) {
    $href = trim(html_entity_decode((string) $anchors->item($j)->nodeValue, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($href === '') {
      continue;
    }
    $path = (string) (parse_url($href, PHP_URL_PATH) ?? '');
    if (!preg_match('#/\d{4}/\d{2}/#', $path)) {
      continue;
    }
    if (isset($seen[$href])) {
      continue;
    }
    $seen[$href] = TRUE;
    $hrefs[] = $href;
  }
  return $hrefs;
}

function df_issue_page_wp_url_to_node_id(string $wp_url): ?int {
  $wp_url = df_issue_page_canonical_url($wp_url);
  $norm_path = df_issue_page_normalize_wp_path($wp_url);
  if ($norm_path === '') {
    return NULL;
  }

  $alias_manager = \Drupal::service('path_alias.manager');
  foreach (df_issue_page_drupal_alias_candidates($norm_path) as $candidate) {
    $candidate = '/' . trim($candidate, '/');
    if ($candidate === '/') {
      continue;
    }
    $internal = $alias_manager->getPathByAlias($candidate);
    if (preg_match('#^/node/(\d+)$#', $internal, $m)) {
      return (int) $m[1];
    }
  }
  return NULL;
}

/**
 * Normalizes a WordPress request path to a lowercase string starting with /.
 */
function df_issue_page_normalize_wp_path(string $url): string {
  $path = (string) (parse_url(trim($url), PHP_URL_PATH) ?? '');
  $path = rawurldecode($path);
  $path = '/' . trim($path, '/');
  if ($path === '/') {
    return '';
  }
  return strtolower($path);
}

/**
 * Suggested Drupal path aliases for a normalized WordPress path.
 *
 * Drupal uses /stories/{slug} (see articles.json drupal_alias), not /YYYY/MM/slug.
 */
function df_issue_page_drupal_alias_candidates(string $normalized_wp_path): array {
  $maps = df_issue_page_load_path_maps();
  $out = [];

  if (isset($maps['full'][$normalized_wp_path])) {
    $out[] = $maps['full'][$normalized_wp_path];
  }

  $slug = df_issue_page_slug_from_wp_path($normalized_wp_path);
  if ($slug !== '') {
    if (isset($maps['slug'][$slug])) {
      $out[] = $maps['slug'][$slug];
    }
    $out[] = '/stories/' . $slug;
  }

  $out[] = $normalized_wp_path;

  return array_values(array_unique(array_filter($out)));
}

function df_issue_page_slug_from_wp_path(string $normalized_wp_path): string {
  $trimmed = trim($normalized_wp_path, '/');
  if ($trimmed === '') {
    return '';
  }
  $parts = explode('/', $trimmed);
  return (string) end($parts);
}

/**
 * Loads wp_path => drupal_alias and slug => drupal_alias from migrate JSON.
 *
 * @return array{full: array<string, string>, slug: array<string, string>}
 */
function df_issue_page_load_path_maps(): array {
  static $cache = NULL;
  if ($cache !== NULL) {
    return $cache;
  }

  $cache = [
    'full' => [],
    'slug' => [],
  ];

  $root = \Drupal::root();
  $articles_file = $root . '/modules/custom/df_migrate/data/articles.json';
  if (is_readable($articles_file)) {
    $rows = json_decode((string) file_get_contents($articles_file), TRUE);
    if (is_array($rows)) {
      foreach ($rows as $row) {
        if (empty($row['drupal_alias']) || !is_string($row['drupal_alias'])) {
          continue;
        }
        $drupal = $row['drupal_alias'];
        if (!empty($row['wp_url']) && is_string($row['wp_url'])) {
          $p = df_issue_page_normalize_wp_path($row['wp_url']);
          if ($p !== '') {
            $cache['full'][$p] = $drupal;
          }
        }
        if (!empty($row['slug']) && is_string($row['slug'])) {
          $cache['slug'][$row['slug']] = $drupal;
        }
      }
    }
  }

  $redirect_file = $root . '/modules/custom/df_migrate/data/redirects.json';
  if (is_readable($redirect_file)) {
    $rows = json_decode((string) file_get_contents($redirect_file), TRUE);
    if (is_array($rows)) {
      foreach ($rows as $row) {
        if (empty($row['source']) || empty($row['redirect_to'])
          || !is_string($row['source']) || !is_string($row['redirect_to'])) {
          continue;
        }
        $slug = basename(trim(rawurldecode($row['source']), '/'));
        if ($slug === '' || isset($cache['slug'][$slug])) {
          continue;
        }
        $cache['slug'][$slug] = $row['redirect_to'];
      }
    }
  }

  return $cache;
}

function df_issue_page_canonical_url(string $url): string {
  $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
  if (preg_match('#https?://i\d*\.wp\.com/danafarberimpact\.org(/.+)#i', $url, $m)) {
    $path = preg_replace('/\?.*$/', '', $m[1]);
    return 'https://danafarberimpact.org' . $path;
  }
  $parts = parse_url($url);
  if (!empty($parts['scheme']) && !empty($parts['host']) && !empty($parts['path'])) {
    $out = $parts['scheme'] . '://' . $parts['host'] . $parts['path'];
    return $out;
  }
  return $url;
}

function df_issue_page_get_or_create_media(int $fid, string $alt): ?int {
  $existing = \Drupal::entityQuery('media')
    ->condition('bundle', 'image')
    ->condition('field_media_image.target_id', $fid)
    ->accessCheck(FALSE)
    ->range(0, 1)
    ->execute();
  if (!empty($existing)) {
    return (int) reset($existing);
  }
  /** @var \Drupal\file\FileInterface|null $file */
  $file = \Drupal::entityTypeManager()->getStorage('file')->load($fid);
  if (!$file instanceof FileInterface) {
    return NULL;
  }
  $alt = mb_substr($alt, 0, 512);
  $media = Media::create([
    'bundle' => 'image',
    'uid' => 1,
    'name' => $file->getFilename(),
    'field_media_image' => [
      'target_id' => $fid,
      'alt' => $alt !== '' ? $alt : 'Issue banner',
    ],
    'status' => 1,
  ]);
  $media->save();
  return (int) $media->id();
}
