<?php

/**
 * @file
 * One-time decode of HTML entities in node, media, menu, and taxonomy text fields.
 */

use Drupal\df_migrate\TextEntityHelper;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

/**
 * @return array{scanned: int, updated: int, entities_before: int, entities_after: int, leftovers_before: int, leftovers_after: int}
 */
function df_migrate_decode_text_entities_run(bool $dry_run = FALSE): array {
  $entities_before = 0;
  $entities_after = 0;
  $leftovers_before = 0;
  $leftovers_after = 0;
  $updated = 0;
  $scanned = 0;

  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $nids = $storage->getQuery()->accessCheck(FALSE)->execute();
  foreach ($storage->loadMultiple($nids) as $node) {
    if (!$node instanceof Node) {
      continue;
    }
    $scanned++;
    $changed = FALSE;
    $entities_before += TextEntityHelper::countEntityReferences($node->label());
    $leftovers_before += TextEntityHelper::countLeftoverPatterns($node->label());
    $title = TextEntityHelper::decode($node->label());
    if ($title !== $node->label()) {
      $node->setTitle($title);
      $changed = TRUE;
    }

    foreach (['field_excerpt', 'field_summary', 'field_meta_description'] as $field) {
      if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
        continue;
      }
      $raw = (string) ($node->get($field)->value ?? '');
      $entities_before += TextEntityHelper::countEntityReferences($raw);
      $leftovers_before += TextEntityHelper::countLeftoverPatterns($raw);
      $is_html = in_array($field, ['field_summary'], TRUE);
      $decoded = $is_html ? TextEntityHelper::decodeHtmlField($raw) : TextEntityHelper::decode(strip_tags($raw));
      if ($decoded !== $raw) {
        $node->set($field, ['value' => $decoded, 'format' => $node->get($field)->format ?? 'plain_text']);
        $changed = TRUE;
      }
    }

    if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
      $raw = (string) $node->get('body')->value;
      $entities_before += TextEntityHelper::countEntityReferences($raw);
      $leftovers_before += TextEntityHelper::countLeftoverPatterns($raw);
      $decoded = TextEntityHelper::decodeHtmlField($raw);
      if ($decoded !== $raw) {
        $node->set('body', ['value' => $decoded, 'format' => $node->get('body')->format ?? 'full_html']);
        $changed = TRUE;
      }
    }

    $entities_after += TextEntityHelper::countEntityReferences($node->label());
    $leftovers_after += TextEntityHelper::countLeftoverPatterns($node->label());
    if ($node->hasField('field_excerpt') && !$node->get('field_excerpt')->isEmpty()) {
      $v = (string) $node->get('field_excerpt')->value;
      $entities_after += TextEntityHelper::countEntityReferences($v);
      $leftovers_after += TextEntityHelper::countLeftoverPatterns($v);
    }

    if ($changed && !$dry_run) {
      $node->save();
      $updated++;
    }
    elseif ($changed) {
      $updated++;
    }
  }

  $media_storage = \Drupal::entityTypeManager()->getStorage('media');
  $mids = $media_storage->getQuery()->accessCheck(FALSE)->execute();
  foreach ($media_storage->loadMultiple($mids) as $media) {
    if (!$media instanceof Media || !$media->hasField('field_media_image')) {
      continue;
    }
    $scanned++;
    $item = $media->get('field_media_image')->first();
    if ($item === NULL) {
      continue;
    }
    $alt = (string) ($item->alt ?? '');
    $entities_before += TextEntityHelper::countEntityReferences($alt);
    $leftovers_before += TextEntityHelper::countLeftoverPatterns($alt);
    $decoded_alt = TextEntityHelper::decode($alt);
    $changed = FALSE;
    if ($decoded_alt !== $alt) {
      $item->alt = $decoded_alt;
      $changed = TRUE;
    }
    if ($media->hasField('field_media_caption') && !$media->get('field_media_caption')->isEmpty()) {
      $cap = (string) $media->get('field_media_caption')->value;
      $entities_before += TextEntityHelper::countEntityReferences($cap);
      $leftovers_before += TextEntityHelper::countLeftoverPatterns($cap);
      $decoded_cap = TextEntityHelper::decode($cap);
      if ($decoded_cap !== $cap) {
        $media->set('field_media_caption', $decoded_cap);
        $changed = TRUE;
      }
    }
    if ($changed && !$dry_run) {
      $media->save();
      $updated++;
    }
    elseif ($changed) {
      $updated++;
    }
  }

  $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
  $tids = $term_storage->getQuery()->accessCheck(FALSE)->execute();
  foreach ($term_storage->loadMultiple($tids) as $term) {
    if (!$term instanceof Term) {
      continue;
    }
    $scanned++;
    $name = $term->label();
    $entities_before += TextEntityHelper::countEntityReferences($name);
    $leftovers_before += TextEntityHelper::countLeftoverPatterns($name);
    $decoded = TextEntityHelper::decode($name);
    $changed = FALSE;
    if ($decoded !== $name) {
      $term->setName($decoded);
      $changed = TRUE;
    }
    if ($term->hasField('description') && !$term->get('description')->isEmpty()) {
      $desc = (string) $term->get('description')->value;
      $entities_before += TextEntityHelper::countEntityReferences($desc);
      $leftovers_before += TextEntityHelper::countLeftoverPatterns($desc);
      $decoded_desc = TextEntityHelper::decodeHtmlField($desc);
      if ($decoded_desc !== $desc) {
        $term->set('description', ['value' => $decoded_desc, 'format' => $term->get('description')->format ?? 'plain_text']);
        $changed = TRUE;
      }
    }
    if ($changed && !$dry_run) {
      $term->save();
      $updated++;
    }
    elseif ($changed) {
      $updated++;
    }
  }

  $menu_storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
  $mlids = $menu_storage->getQuery()->accessCheck(FALSE)->execute();
  foreach ($menu_storage->loadMultiple($mlids) as $link) {
    $scanned++;
    $title = (string) $link->label();
    $entities_before += TextEntityHelper::countEntityReferences($title);
    $leftovers_before += TextEntityHelper::countLeftoverPatterns($title);
    $decoded = TextEntityHelper::decode($title);
    if ($decoded !== $title) {
      if (!$dry_run) {
        $link->setTitle($decoded);
        $link->save();
      }
      $updated++;
    }
  }

  return [
    'scanned' => $scanned,
    'updated' => $updated,
    'entities_before' => $entities_before,
    'entities_after' => $entities_after,
    'leftovers_before' => $leftovers_before,
    'leftovers_after' => $leftovers_after,
  ];
}
