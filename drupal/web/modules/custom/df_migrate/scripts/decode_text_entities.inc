<?php

/**
 * @file
 * One-time decode of HTML entities in node plain-text fields.
 */

use Drupal\df_migrate\TextEntityHelper;
use Drupal\node\Entity\Node;

/**
 * @return array{scanned: int, updated: int, entities_before: int, entities_after: int}
 */
function df_migrate_decode_text_entities_run(bool $dry_run = FALSE): array {
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $nids = $storage->getQuery()->accessCheck(FALSE)->execute();
  $updated = 0;
  $entities_before = 0;
  $entities_after = 0;
  foreach ($storage->loadMultiple($nids) as $node) {
    if (!$node instanceof Node) {
      continue;
    }
    $changed = FALSE;
    $entities_before += TextEntityHelper::countEntityReferences($node->label());
    $title = TextEntityHelper::decode($node->label());
    if ($title !== $node->label()) {
      $node->setTitle($title);
      $changed = TRUE;
    }
    foreach (['field_excerpt', 'field_summary'] as $field) {
      if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
        continue;
      }
      $raw = (string) ($node->get($field)->value ?? '');
      $entities_before += TextEntityHelper::countEntityReferences($raw);
      $decoded = TextEntityHelper::decode(strip_tags($raw));
      if ($decoded !== strip_tags($raw)) {
        $node->set($field, ['value' => $decoded, 'format' => $node->get($field)->format ?? 'plain_text']);
        $changed = TRUE;
      }
    }
    $entities_after += TextEntityHelper::countEntityReferences($node->label());
    if ($node->hasField('field_excerpt') && !$node->get('field_excerpt')->isEmpty()) {
      $entities_after += TextEntityHelper::countEntityReferences((string) $node->get('field_excerpt')->value);
    }
    if ($changed && !$dry_run) {
      $node->save();
      $updated++;
    }
    elseif ($changed) {
      $updated++;
    }
  }
  return [
    'scanned' => count($nids),
    'updated' => $updated,
    'entities_before' => $entities_before,
    'entities_after' => $entities_after,
  ];
}
