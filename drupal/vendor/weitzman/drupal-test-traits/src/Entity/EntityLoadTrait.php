<?php

declare(strict_types=1);

namespace weitzman\DrupalTestTraits\Entity;

use Drupal\Core\Entity\EntityInterface;

/**
 * Helper trait for loading entities.
 */
trait EntityLoadTrait
{
    /**
     * Load an entity by some properties.
     *
     * @return ($only_one is true ? \Drupal\Core\Entity\EntityInterface|null : \Drupal\Core\Entity\EntityInterface[])
     *   A loaded entity or NULL if $only_one is TRUE, or an array of entities.
     */
    protected function loadEntityByProperty(string $entity_type_id, array $properties, bool $only_one = true): EntityInterface|array|null
    {
        $type_manager = \Drupal::entityTypeManager();
        $storage = $type_manager->getStorage($entity_type_id);
        $storage->resetCache();
        $entities = $storage->loadByProperties($properties);
        return $only_one ? (\reset($entities) ?: null) : $entities;
    }

    /**
     * Load the last created entity of a given type.
     */
    protected function lastCreatedEntity(string $type): ?EntityInterface
    {
        $type_manager = \Drupal::entityTypeManager();
        $id_key = $type_manager->getDefinition($type)->getKey('id');
        $results = \Drupal::entityQuery($type)->sort($id_key, 'DESC')->range(0, 1)->accessCheck(false)->execute();
        if (empty($results)) {
            return null;
        }
        $id = \array_shift($results);
        return $type_manager->getStorage($type)->load($id);
    }
}
