<?php

declare(strict_types=1);

namespace Drupal\df_setup;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Ensures the Channels vocabulary has the Digital Exclusives term used by Views.
 */
final class DigitalExclusiveChannels {

  private const VOCABULARY_ID = 'channels';

  private const TERM_NAME = 'Digital Exclusives';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Loads term ID by vocabulary + name, or creates the term when missing.
   *
   * @return int|null
   *   The taxonomy term ID, or NULL when the vocabulary is not installed.
   */
  public function ensureTermId(): ?int {
    $existing = $this->findTermId();
    if ($existing !== NULL) {
      return $existing;
    }

    if (!Vocabulary::load(self::VOCABULARY_ID)) {
      return NULL;
    }

    $term = Term::create([
      'vid' => self::VOCABULARY_ID,
      'name' => self::TERM_NAME,
      'status' => 1,
    ]);
    $term->save();
    return (int) $term->id();
  }

  /**
   * Looks up the Digital Exclusives term ID without creating it.
   */
  public function findTermId(): ?int {
    if (!Vocabulary::load(self::VOCABULARY_ID)) {
      return NULL;
    }

    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $tids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', self::VOCABULARY_ID)
      ->condition('name', self::TERM_NAME)
      ->range(0, 1)
      ->execute();

    if (!$tids) {
      return NULL;
    }

    return (int) reset($tids);
  }

}
